<?php

declare(strict_types=1);

namespace SuperInstance\FluxVM;

use SuperInstance\FluxVM\FluxVMException;

final class Loader
{
    private const MAGIC = 'FLUX';
    private const VERSION = 3;

    /**
     * Load FLUX binary bytecode into a byte array.
     *
     * FLUX binary format:
     * - 4-byte magic: 'F' 'L' 'U' 'X' (0x46, 0x4C, 0x55, 0x58)
     * - 1-byte version: 0x03
     * - 4-byte code size (little-endian)
     * - Code bytes (code size)
     */
    public static function load(string $filename): string
    {
        if (!file_exists($filename)) {
            throw FluxVMException::invalidBytecode("File not found: $filename");
        }

        $data = file_get_contents($filename);
        if ($data === false) {
            throw FluxVMException::invalidBytecode("Cannot read file: $filename");
        }

        return self::parse($data);
    }

    public static function fromString(string $data): string
    {
        return self::parse($data);
    }

    private static function parse(string $data): string
    {
        $len = strlen($data);

        // Minimum: magic(4) + version(1) + size(4) = 9 bytes
        if ($len < 9) {
            throw FluxVMException::invalidBytecode('File too small for FLUX header');
        }

        // Check magic
        $magic = substr($data, 0, 4);
        if ($magic !== self::MAGIC) {
            throw FluxVMException::invalidBytecode(
                'Invalid FLUX magic: expected FLUX, got ' . bin2hex($magic)
            );
        }

        // Check version
        $version = ord($data[4]);
        if ($version !== self::VERSION) {
            throw FluxVMException::invalidBytecode(
                "Unsupported FLUX version: $version (expected " . self::VERSION . ")"
            );
        }

        // Read code size (4 bytes, little-endian)
        $size = ord($data[5]) | (ord($data[6]) << 8) | (ord($data[7]) << 16) | (ord($data[8]) << 24);

        // Verify we have enough data
        $expected = 9 + $size;
        if ($len < $expected) {
            throw FluxVMException::invalidBytecode(
                "Incomplete bytecode: expected $expected bytes, got $len"
            );
        }

        // Extract code
        return substr($data, 9, $size);
    }

    /**
     * Create a FLUX binary bytecode file from code bytes.
     */
    public static function create(string $code): string
    {
        $size = strlen($code);

        // Build header
        $header = self::MAGIC;
        $header .= chr(self::VERSION);
        $header .= chr($size & 0xFF);
        $header .= chr(($size >> 8) & 0xFF);
        $header .= chr(($size >> 16) & 0xFF);
        $header .= chr(($size >> 24) & 0xFF);

        return $header . $code;
    }

    /**
     * Validate bytecode without loading it.
     */
    public static function validate(string $filename): bool
    {
        try {
            self::load($filename);
            return true;
        } catch (FluxVMException) {
            return false;
        }
    }
}