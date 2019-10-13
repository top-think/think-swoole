<?php

namespace think\swoole\command;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Helpers;
use Nette\PhpGenerator\PhpFile;
use Swoole\Coroutine\Client;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\swoole\contract\rpc\ParserInterface;
use think\swoole\rpc\JsonParser;
use think\swoole\rpc\server\Dispatcher;

class RpcInterface extends Command
{
    public function configure()
    {
        $this->setName('rpc:interface')
            ->setDescription('Generate Rpc Service Interfaces');
    }

    protected function initialize(Input $input, Output $output)
    {
        $parserClass = $this->app->config->get('swoole.rpc.server.parser', JsonParser::class);
        $this->app->bind(ParserInterface::class, $parserClass);
    }

    public function handle(ParserInterface $parser)
    {
        go(function () use ($parser) {
            $clients = $this->app->config->get('swoole.rpc.client', []);

            $file = new PhpFile;
            $file->addComment('This file is auto-generated.');
            $file->setStrictTypes();

            foreach ($clients as $name => $config) {
                $client = new Client(SWOOLE_SOCK_TCP);
                if ($client->connect($config['host'], $config['port'])) {
                    $client->send(Dispatcher::ACTION_INTERFACE);
                    $response = $client->recv();
                    $result   = $parser->decodeResponse($response);
                    $client->close();

                    $namespace = $file->addNamespace("rpc\\contract\\${name}");
                    foreach ($result as $interface => $methods) {
                        $class = $namespace->addInterface($interface);
                        $class->addConstant("RPC", $name);

                        foreach ($methods as $methodName => ['parameters' => $parameters, 'returnType' => $returnType, 'comment' => $comment]) {
                            $method = $class->addMethod($methodName)
                                ->setVisibility(ClassType::VISIBILITY_PUBLIC)
                                ->setComment(Helpers::unformatDocComment($comment))
                                ->setReturnType($returnType);

                            foreach ($parameters as $parameter) {

                                $param = $method->addParameter($parameter['name'])
                                    ->setTypeHint($parameter['type']);

                                if (array_key_exists("default", $parameter)) {
                                    $param->setDefaultValue($parameter['default']);
                                }
                            }
                        }
                    }
                }
            }

            file_put_contents($this->app->getBasePath() . 'rpc.php', $file);

            $this->output->writeln('<info>Succeed!</info>');
        });
    }
}
