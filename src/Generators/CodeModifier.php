<?php

namespace NativeCLI\Generators;

class CodeModifier
{
    protected string $content;

    protected string $filePath;

    public function __construct(string $filePath)
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $this->filePath = $filePath;
        $this->content = file_get_contents($filePath);
    }

    public function backup(): void
    {
        $backupPath = $this->filePath . '.backup';
        file_put_contents($backupPath, $this->content);
    }

    public function hasMethod(string $methodName): bool
    {
        return preg_match('/public\s+function\s+' . preg_quote($methodName, '/') . '\s*\(/', $this->content) === 1;
    }

    public function hasUseStatement(string $class): bool
    {
        $className = $this->extractClassName($class);

        return preg_match('/use\s+' . preg_quote($class, '/') . '\s*;/', $this->content) === 1
            || preg_match('/use\s+[^;]+\\\\' . preg_quote($className, '/') . '\s*;/', $this->content) === 1;
    }

    public function addUseStatement(string $class): void
    {
        if ($this->hasUseStatement($class)) {
            return;
        }

        // Find all use statements
        preg_match_all('/^use\s+[^;]+;$/m', $this->content, $matches, PREG_OFFSET_CAPTURE);

        if (empty($matches[0])) {
            // No use statements, add after namespace declaration
            $this->content = preg_replace(
                '/(namespace\s+[^;]+;)/',
                "$1\n\nuse {$class};",
                $this->content,
                1
            );
            return;
        }

        // Find the correct position to insert based on alphabetical order
        $insertAfter = null;

        foreach ($matches[0] as $match) {
            $existingClass = $this->extractClassFromUseStatement(trim($match[0]));

            if (strcasecmp($class, $existingClass) > 0) {
                // New class comes after this one alphabetically
                $insertAfter = $match;
            } else {
                // Found first class that comes after the new one, stop here
                break;
            }
        }

        if ($insertAfter !== null) {
            // Insert after the last use statement that comes before this one alphabetically
            $insertPosition = $insertAfter[1] + strlen($insertAfter[0]);
            $this->content = substr_replace(
                $this->content,
                "\nuse {$class};",
                $insertPosition,
                0
            );
        } else {
            // New class comes before all existing use statements
            $firstUse = $matches[0][0];
            $insertPosition = $firstUse[1];
            $this->content = substr_replace(
                $this->content,
                "use {$class};\n",
                $insertPosition,
                0
            );
        }
    }

    protected function extractClassFromUseStatement(string $useStatement): string
    {
        // Extract the class name from "use Foo\Bar\Class;"
        preg_match('/use\s+([^;]+);/', $useStatement, $matches);
        return trim($matches[1] ?? '');
    }

    public function insertIntoMethod(string $methodName, string $code, string $position = 'end'): void
    {
        if (!$this->hasMethod($methodName)) {
            throw new \RuntimeException("Method {$methodName} not found in file");
        }

        // Find the method
        $pattern = '/(public\s+function\s+' . preg_quote($methodName, '/') . '\s*\([^)]*\)[^{]*{)/';

        if (!preg_match($pattern, $this->content, $matches, PREG_OFFSET_CAPTURE)) {
            throw new \RuntimeException("Could not parse method {$methodName}");
        }

        $methodStart = $matches[0][1] + strlen($matches[0][0]);

        // Find the closing brace of the method
        $braceCount = 1;
        $pos = $methodStart;
        $contentLength = strlen($this->content);

        while ($braceCount > 0 && $pos < $contentLength) {
            if ($this->content[$pos] === '{') {
                $braceCount++;
            } elseif ($this->content[$pos] === '}') {
                $braceCount--;
            }
            $pos++;
        }

        $methodEnd = $pos - 1;

        if ($position === 'start') {
            // Insert at the beginning of the method
            $insertPosition = $methodStart;
            $codeToInsert = "\n" . $code;
        } else {
            // Insert at the end of the method (before closing brace)
            $insertPosition = $methodEnd;
            $codeToInsert = "\n" . $code;
        }

        $this->content = substr_replace($this->content, $codeToInsert, $insertPosition, 0);
    }

    public function methodContains(string $methodName, string $searchString): bool
    {
        $pattern = '/(public\s+function\s+' . preg_quote($methodName, '/') . '\s*\([^)]*\)[^{]*{)/';

        if (!preg_match($pattern, $this->content, $matches, PREG_OFFSET_CAPTURE)) {
            return false;
        }

        $methodStart = $matches[0][1] + strlen($matches[0][0]);

        // Find the closing brace
        $braceCount = 1;
        $pos = $methodStart;
        $contentLength = strlen($this->content);

        while ($braceCount > 0 && $pos < $contentLength) {
            if ($this->content[$pos] === '{') {
                $braceCount++;
            } elseif ($this->content[$pos] === '}') {
                $braceCount--;
            }
            $pos++;
        }

        $methodContent = substr($this->content, $methodStart, $pos - $methodStart);

        return strpos($methodContent, $searchString) !== false;
    }

    public function save(): void
    {
        file_put_contents($this->filePath, $this->content);
    }

    public function getContent(): string
    {
        return $this->content;
    }

    protected function getIndentationBefore(int $position): string
    {
        $lineStart = strrpos(substr($this->content, 0, $position), "\n");

        if ($lineStart === false) {
            return '';
        }

        $lineStart++; // Move past the newline
        $indent = '';

        for ($i = $lineStart; $i < $position; $i++) {
            if ($this->content[$i] === ' ' || $this->content[$i] === "\t") {
                $indent .= $this->content[$i];
            } else {
                break;
            }
        }

        return $indent;
    }

    protected function indentCode(string $code, string $indent): string
    {
        $lines = explode("\n", $code);
        $indentedLines = [];

        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $indentedLines[] = $indent . $line;
            } else {
                $indentedLines[] = $line;
            }
        }

        return implode("\n", $indentedLines);
    }

    protected function extractClassName(string $fullyQualifiedClass): string
    {
        $parts = explode('\\', $fullyQualifiedClass);

        return end($parts);
    }
}
