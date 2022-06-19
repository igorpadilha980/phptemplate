<?php

    class TemplateString {

        private array $parts;
        private array $content;

        public function __construct(array $parts) {
            $this->parts = $parts;
            $this->content = array_fill(0, count($this->parts) - 1, null);
        }

        public function set(int $position, mixed $value) {
            $this->content[$position] = $value;
        }

        public function build(): string {
            $result = $this->parts[0];
            foreach($this->content as $key => $value) {
                $result .= $value . $this->parts[$key+1];
            }
            return $result;
        }

        public function getSpacesCount(): int {
            return count($this->content);
        }

        public function copy(): TemplateString {
            return new TemplateString($this->parts);
        }

    }

    class TemplateStringBuilder {

        private array $parts = [];

        public function append(string $part): void {
            $this->parts[] = $part;
        }

        public function build(): TemplateString {
            return new TemplateString($this->parts);
        }

        public function getSpacesCount(): int {
            return count($this->parts) - 1;
        }

    }
