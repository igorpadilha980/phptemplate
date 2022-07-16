<?php

    require_once(__DIR__ . "/InjectableString.php");

    class TemplateLoader {

        static function load(string $path): string {
            $file = fopen($path, "r");
            $content = fread($file, filesize($path));
            fclose($file);

            return $content;
        }

    }

    class TemplateInjectionPoint {
        private bool $reference;
        private string $key;
        private array $positions = [];

        function __construct(bool $reference, string $key, int $position) {
            $this->reference = $reference;
            $this->key = $key;
            $this->positions[] = $position;
        }

        function getKey(): string {
            return $this->key;
        }

        function isReference(): bool {
            return $this->reference;
        }

        function getPositions(): array {
            return $this->positions;
        }

        function appendPosition(int $position) {
            $this->positions[] = $position;
        }
    }

    

    class Template extends GenericInjectableString {

        private static array $GLOBAL_PROPERTIES = [];

        private static array $templateCache = [];
        private static string $identifierPattern = "/\{\{temp:[A-Za-z0-9_]+\}\}|\{\{[A-Za-z0-9_]+\}\}/";

        private array $injectionPoints = [];

        private function __construct(string $content) {
            parent::__construct(self::$identifierPattern, $content);
        }
        
        public static function register(string $templateName, string $templatePath): void {
            self::$templateCache[$templateName] = $templatePath;
        }

        public static function load(string $templateName): Template {
            $path = self::getTemplatePath($templateName);
            return new Template(TemplateLoader::load($path));
        }

        public static function setGlobal(string $propertyName, string $value): void {
            self::$GLOBAL_PROPERTIES[$propertyName] = $value;
        }

        public static function getGlobal(string $propertyName): string {
            return self::$GLOBAL_PROPERTIES[$propertyName];
        }

        private static function getTemplatePath($templateName): string {
            return self::$templateCache[$templateName];
        }

        private static function isTemplateReference(string $key): bool {
            return str_starts_with($key, "{{temp:");
        }

        private static function cleanTemplateReference(string $reference): string {
            $start = strlen("{{temp:");
            $end = strlen($reference) - strlen("]}") - $start;;

            return substr($reference, $start, $end);
        }

        private static function cleanInjectionPoint(string $text): string {
            $start = strlen("{{");
            $end = strlen($text) - strlen("]}") - $start;
    
            return substr($text, $start, $end);
        }

        private function createInjectionPoint(string $matchedString, int $position): void {

            $reference = self::isTemplateReference($matchedString);
            $key = $reference? self::cleanTemplateReference($matchedString) : self::cleanInjectionPoint($matchedString);

            $this->injectionPoints[$key] = new TemplateInjectionPoint($reference, $key, $position);
        }

        function processMatch(string $matchedString, int $position): void {
            $reference = self::isTemplateReference($matchedString);
            $key = $reference? self::cleanTemplateReference($matchedString) : self::cleanInjectionPoint($matchedString);

            if(array_key_exists($key, $this->injectionPoints)) {
                $this->injectionPoints[$key]->appendPosition($position);
            } else {
                $this->createInjectionPoint($matchedString, $position);
            }
        }

        private function renderTemplate(Template $template, array|Properties $data):string {
            $data = is_array($data)? $data : [$data];
            $result = "";
            foreach($data as $props) {
                $template->fill(new Properties($props));
                $result .= $template->get();
                $template->clean();
            }

            return $result;
        }

        private function processInjectionPoint(TemplateInjectionPoint $point, string|Properties|array $data): string {
            if($point->isReference()) {
                return $this->renderTemplate(self::load($point->getKey()), $data);
            } else {
                return $data;
            }
        }

        private function resolveValue(string $key): string {
            if(array_key_exists($key, $this->parameters)) {
                return $this->parameters[$key];
            } elseif(array_key_exists($key, self::$GLOBAL_PROPERTIES)) {
                return self::getGlobal($key);
            } else {
                throw new \Exception("unset property '$key'");
            }
        }

        public function get(): string {

            foreach($this->injectionPoints as $key => $point) {
                $value = $this->resolveValue($key);
                $output = $this->processInjectionPoint($point, $value);

                foreach($point->getPositions() as $position) {
                    $this->template->set($position, $output);
                }
            }

            return $this->template->build();
        }
    }

?>