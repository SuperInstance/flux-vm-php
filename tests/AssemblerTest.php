<?php

namespace FluxVM\Tests;

use FluxVM\Assembler;
use PHPUnit\Framework\TestCase;

class AssemblerTest extends TestCase
{
    private Assembler $assembler;

    protected function setUp(): void
    {
        $this->assembler = new Assembler();
    }

    public function testBasicInstructionAssembly(): void
    {
        // Test Format A: NOP (0x00)
        $result = $this->assembler->assemble('NOP');
        $this->assertEquals([0x00], $result);
        
        // Test Format B: HALT (0x01)
        $result = $this->assembler->assemble('HALT');
        $this->assertEquals([0x01], $result);
    }

    public function testFormatG_JumpInstruction(): void
    {
        // Jump R0, +5 (relative forward)
        $result = $this->assembler->assemble('Jump R0 +5');
        $this->assertCount(5, $result);
        $this->assertEquals(0x03, $result[0]); // opcode
        $this->assertEquals(0x05, $result[1]); // length
    }

    public function testFormatG_CallInstruction(): void
    {
        // Call R1, -10 (relative backward)
        $result = $this->assembler->assemble('Call R1 -10');
        $this->assertCount(5, $result);
        $this->assertEquals(0x04, $result[0]); // opcode
    }

    public function testInvalidOpcodeHandling(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->assembler->assemble('INVALID_OP');
    }

    public function testMultipleInstructions(): void
    {
        $program = "NOP\nHALT";
        $result = $this->assembler->assembleProgram($program);
        $this->assertEquals([0x00, 0x01], $result);
    }
}
