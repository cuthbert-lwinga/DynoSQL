<?php

class SqlColumn {
    private string $name;
    private string $type;
    private bool $isPrimary;
    private bool $isNullable;
    private $default;
    private bool $isUnique;
    private bool $isAutoIncrement;
    private ?int $length;
    private ?string $comment;

    public function __construct(
        string $name, 
        string $type, 
        bool $isPrimary = false, 
        bool $isNullable = true, 
        $default = null, 
        bool $isUnique = false, 
        bool $isAutoIncrement = false, 
        ?int $length = null, 
        ?string $comment = null
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->isPrimary = $isPrimary;
        $this->isNullable = $isNullable;
        $this->default = $default;
        $this->isUnique = $isUnique;
        $this->isAutoIncrement = $isAutoIncrement;
        $this->length = $length;
        $this->comment = $comment;
    }

    // Getters
    public function getName(): string {
        return $this->name;
    }

    public function getType(): string {
        return $this->type;
    }

    public function isPrimary(): bool {
        return $this->isPrimary;
    }

    public function isNullable(): bool {
        return $this->isNullable;
    }

    public function getDefault() {
        return $this->default;
    }

    public function isUnique(): bool {
        return $this->isUnique;
    }

    public function isAutoIncrement(): bool {
        return $this->isAutoIncrement;
    }

    public function getLength(): ?int {
        return $this->length;
    }

    public function getComment(): ?string {
        return $this->comment;
    }

    // Setters
    public function setName(string $name): void {
        $this->name = $name;
    }

    public function setType(string $type): void {
        $this->type = $type;
    }

    public function setPrimary(bool $isPrimary): void {
        $this->isPrimary = $isPrimary;
    }

    public function setNullable(bool $isNullable): void {
        $this->isNullable = $isNullable;
    }

    public function setDefault($default): void {
        $this->default = $default;
    }

    public function setUnique(bool $isUnique): void {
        $this->isUnique = $isUnique;
    }

    public function setAutoIncrement(bool $isAutoIncrement): void {
        $this->isAutoIncrement = $isAutoIncrement;
    }

    public function setLength(?int $length): void {
        $this->length = $length;
    }

    public function setComment(?string $comment): void {
        $this->comment = $comment;
    }
}
?>