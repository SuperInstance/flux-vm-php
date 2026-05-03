<?php

declare(strict_types=1);

namespace SuperInstance\FluxVM;

require_once __DIR__ . '/../vendor/autoload.php';

$usage = <<<USAGE
FLUX ISA v3.0 Virtual Machine
===========================

Usage:
    php src/CLI.php run <bytecode_file>     Run FLUX bytecode file
    php src/CLI.php asm <asm_file>         Assemble FLUX assembly to bytecode
    php src/CLI.php dis <bytecode_file>    Disassemble bytecode to assembly
    php src/CLI.php repl                   Start interactive REPL

Examples:
    php src/CLI.php run program.flux
    php src/CLI.php asm program.asm
    php src/CLI.php dis program.flux
    php src/CLI.php repl

USAGE;

if ($argc < 2) {
    echo $usage;
    exit(1);
}

$command = $argv[1];

try {
    match ($command) {
        'run' => runCommand($argv),
        'asm' => asmCommand($argv),
        'dis' => disCommand($argv),
        'repl' => replCommand(),
        default => (function() { echo "Unknown command: $command\n"; echo $usage; exit(1); })()
    };
} catch (FluxVMException $e) {
    fwrite(STDERR, "FLUX Error: " . $e->getMessage() . "\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}

function runCommand(array $argv): void
{
    if (!isset($argv[2])) {
        fwrite(STDERR, "Usage: php src/CLI.php run <bytecode_file>\n");
        exit(1);
    }

    $filename = $argv[2];

    // Load bytecode (with or without FLUX header)
    $data = file_get_contents($filename);
    if ($data === false) {
        fwrite(STDERR, "Cannot read file: $filename\n");
        exit(1);
    }

    // Check if it has FLUX header
    if (str_starts_with($data, 'FLUX')) {
        $bytecode = Loader::fromString($data);
    } else {
        // Raw bytecode without header
        $bytecode = $data;
    }

    $vm = new FluxVM();
    $vm->load($bytecode);

    echo "Running $filename...\n";
    echo "Initial state: " . $vm->getState()->value . "\n";

    try {
        $vm->run();
    } catch (Throwable $e) {
        // Error already handled by VM state
    }

    echo "\nFinal state: " . $vm->getState()->value . "\n";
    echo "\nRegisters:\n";
    $regs = $vm->regs();
    echo "  GP: " . json_encode(array_slice($regs['GP'], 0, 8)) . "\n";
    echo "  SP: " . $regs['SP'] . ", PC: " . $regs['PC'] . "\n";
    echo "\nStats: " . json_encode($vm->stats()) . "\n";
}

function asmCommand(array $argv): void
{
    if (!isset($argv[2])) {
        fwrite(STDERR, "Usage: php src/CLI.php asm <asm_file>\n");
        exit(1);
    }

    $filename = $argv[2];

    if (!file_exists($filename)) {
        fwrite(STDERR, "File not found: $filename\n");
        exit(1);
    }

    $source = file_get_contents($filename);
    if ($source === false) {
        fwrite(STDERR, "Cannot read file: $filename\n");
        exit(1);
    }

    echo "Assembling $filename...\n";

    $bytecode = Assembler::assemble($source);
    $binary = Loader::create($bytecode);

    $outputFile = preg_replace('/\.(asm|txt)$/', '.flux', $filename);
    file_put_contents($outputFile, $binary);

    echo "Output written to: $outputFile\n";
    echo "Bytecode size: " . strlen($bytecode) . " bytes\n";

    // Show disassembly
    echo "\nDisassembly:\n";
    echo Disassembler::disassemble($bytecode) . "\n";
}

function disCommand(array $argv): void
{
    if (!isset($argv[2])) {
        fwrite(STDERR, "Usage: php src/CLI.php dis <bytecode_file>\n");
        exit(1);
    }

    $filename = $argv[2];

    if (!file_exists($filename)) {
        fwrite(STDERR, "File not found: $filename\n");
        exit(1);
    }

    $data = file_get_contents($filename);
    if ($data === false) {
        fwrite(STDERR, "Cannot read file: $filename\n");
        exit(1);
    }

    // Check if it has FLUX header
    if (str_starts_with($data, 'FLUX')) {
        $bytecode = Loader::fromString($data);
    } else {
        $bytecode = $data;
    }

    echo "Disassembly of $filename:\n";
    echo Disassembler::disassemble($bytecode) . "\n";
}

function replCommand(): void
{
    echo "FLUX ISA v3.0 REPL\n";
    echo "Type 'help' for commands, 'quit' to exit\n\n";

    $vm = new FluxVM();
    $assembler = new Assembler();
    $running = true;

    while ($running) {
        echo "flux> ";
        $line = trim(fgets(STDIN) ?: '');

        if ($line === '' || $line === 'quit' || $line === 'exit') {
            $running = false;
            continue;
        }

        if ($line === 'help') {
            echo <<<HELP
Commands:
  help          Show this help
  quit          Exit REPL
  regs          Show registers
  mem <start> <len>  Show memory
  stats         Show VM stats
  reset         Reset VM state
  run <hex>     Run raw bytecode (space-separated hex bytes)
  asm <source>  Assemble and run FLUX assembly

Examples:
  regs
  mem 0 16
  run 00 01 02 03
  asm IAdd R8, R9, R10

HELP;
            continue;
        }

        if ($line === 'regs') {
            print_r($vm->regs());
            continue;
        }

        if ($line === 'stats') {
            print_r($vm->stats());
            continue;
        }

        if ($line === 'reset') {
            $vm->reset();
            echo "VM reset\n";
            continue;
        }

        if (str_starts_with($line, 'mem ')) {
            $parts = explode(' ', $line);
            $start = (int)($parts[1] ?? 0);
            $len = (int)($parts[2] ?? 16);
            print_r($vm->mem($start, $len));
            continue;
        }

        if (str_starts_with($line, 'run ')) {
            $hex = trim(substr($line, 3));
            $bytes = explode(' ', $hex);
            $bytecode = '';
            foreach ($bytes as $b) {
                $bytecode .= chr((int)hexdec($b));
            }
            $vm->load($bytecode);
            try {
                $vm->run();
            } catch (Throwable $e) {
                echo "Error: " . $e->getMessage() . "\n";
            }
            echo "State: " . $vm->getState()->value . "\n";
            continue;
        }

        if (str_starts_with($line, 'asm ')) {
            $source = trim(substr($line, 3));
            try {
                $bytecode = Assembler::assemble($source);
                $vm->load($bytecode);
                $vm->run();
                echo "State: " . $vm->getState()->value . "\n";
            } catch (Throwable $e) {
                echo "Error: " . $e->getMessage() . "\n";
            }
            continue;
        }

        echo "Unknown command. Type 'help' for commands.\n";
    }
}