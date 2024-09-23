<?php

namespace Lexide\LazyBoy\Lambda;

use Lexide\LazyBoy\Exception\InputException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;

class InputBuilder
{

    /**
     * @param string $command
     * @param string $args
     * @param string $options
     * @param array $event
     * @return InputInterface
     * @throws InputException
     */
    public function buildInput(string $command, string $args, string $options, array $event): InputInterface
    {
        if (empty($command)) {
            throw new InputException("No command was found");
        }

        $args = $this->decodeKeyValue($args, "arg", $event);
        $options = $this->decodeKeyValue($options, "option", $event);

        return new ArrayInput(["command" => $command, ...$options, ...$args]);
    }

    /**
     * @param string $string
     * @param string $type
     * @param array $event
     * @return array
     * @throws InputException
     */
    protected function decodeKeyValue(string $string, string $type, array $event): array
    {
        $kv = [];
        if (!empty($string)) {
            $kv = json_decode($string, true, JSON_THROW_ON_ERROR);
            if (!is_array($kv)) {
                throw new InputException(ucfirst($type) . "s was not an array");
            }
            foreach ($kv as $key => $value) {
                if (is_int($key)) {
                    throw new InputException("Invalid $type");
                }
                if ($type == "option" && !str_starts_with($key, "-")) {
                    // prepend the key with dashes to make it an option
                    $newKey = (strlen($key) == 1 ? "-" : "--") . $key;
                    $kv[$newKey] = $value;
                    unset($kv[$key]);
                    $key = $newKey;
                }
                if (str_starts_with($value, "{event.")) {
                    $eventKey = substr($value, 7, -1);
                    if (empty($event[$eventKey])) {
                        throw new InputException("Event value '$eventKey' does not exist");
                    }
                    $kv[$key] = $event[$eventKey];
                }
            }

        }
        return $kv;
    }

}