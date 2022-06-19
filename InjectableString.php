<?php

require_once(__DIR__ . "/TemplateString.php");

class Properties implements IteratorAggregate, ArrayAccess {
    private array $data = [];

    public function __construct(array|object $data)
    {
        if(is_array($data)) {
            $this->data = $data;
        } elseif($data instanceof Properties) {
            $this->data = $data->data;
        } else {
            $this->data = (array) $data;
        }
    }

    public function set(string $name, mixed $value): Properties {
        $this->data[$name] = $value;
        return $this;
    }

    public function get(string $name) {
        return $this->data[$name];
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->data[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($data[$offset]);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $offset > 0 && $offset < count($this->data);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->data);
    }
}

interface InjectableString
{

    public function set(string $key, string|Properties|array $value): void;

    public function fill(Properties $values): void;

    public function get(): string;

    public function clean(): void;
}

abstract class GenericInjectableString implements InjectableString
{

    protected TemplateString $template;
    protected array $parameters = [];

    public function __construct(string $pattern, string $content)
    {
        $matches = array();
        preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);

        $builder = new TemplateStringBuilder();
        //print_r($matches);
        //echo "<hr>";
        $offset = 0;
        foreach ($matches[0] as $m) {
            //var_dump($m);
            $part = substr($content, 0, $m[1] - $offset);
            //echo "<hr>p $offset <br> " . htmlspecialchars($part) . " len: " . strlen($part);
            $content = substr($content, $m[1] + strlen($m[0]) - $offset, strlen($content));
            //echo "<hr> c<br>" . htmlspecialchars($content) . " len: " . strlen($content);
            //echo "<hr>";
            $builder->append($part);
            $offset = $m[1] + strlen($m[0]);

            $this->processMatch($m[0], $builder->getSpacesCount());
        }

        $builder->append($content);
        //echo "<hr> c<br>" . htmlspecialchars($content) . " len: " . strlen($content);
        //echo "<hr>";
        $this->template = $builder->build();
    }

    public function set(string $key, string|Properties|array $value): void
    {
        $this->parameters[$key] = $value;
    }

    public function fill(Properties $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function clean(): void {
        $this->parameters = [];
    }

    abstract function processMatch(string $matchedString, int $position);
}
