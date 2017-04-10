<?php

declare(strict_types=1);

namespace Chubbyphp\Validation\Error;

interface ErrorInterface
{
    /**
     * @return string
     */
    public function getPath(): string;

    /**
     * @return string
     */
    public function getKey(): string;

    /**
     * @return mixed
     */
    public function getInput();

    /**
     * @return array
     */
    public function getArgs(): array;
}
