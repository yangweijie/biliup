<?php

namespace App\traits;

trait CommandOutput
{
    public function __call(string $name, array $arguments)
    {
        if(method_exists($this->command, $name)){
            call_user_func_array([$this->command, $name], $arguments);
        }
    }
}
