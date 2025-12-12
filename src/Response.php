<?php

declare(strict_types=1);

namespace SulCompany\Router;

class Response
{
    protected int $status = 200;
    protected array $headers = [];
    protected mixed $body = null;
    protected bool $sent = false;

    public function status(int $code): self
    {
        $this->status = $code;
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function json(array $data): void
    {
        $this->header('Content-Type', 'application/json');
        $this->body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->send();
    }

    public function text(string $text): void
    {
        $this->header('Content-Type', 'text/plain; charset=utf-8');
        $this->body = $text;
        $this->send();
    }

    public function html(string $html): void
    {
        $this->header('Content-Type', 'text/html; charset=utf-8');
        $this->body = $html;
        $this->send();
    }

    public function send(): void
    {
        if ($this->sent) return;

        http_response_code($this->status);
        foreach ($this->headers as $k => $v) {
            header("$k: $v");
        }

        if ($this->body !== null) echo $this->body;

        $this->sent = true;
    }
}
