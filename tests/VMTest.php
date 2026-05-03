<?php

declare(strict_types=1);

namespace SuperInstance\FluxVM\Tests;

use PHPUnit\Framework\TestCase;
use SuperInstance\FluxVM\FluxVM;
use SuperInstance\FluxVM\Assembler;
use SuperInstance\FluxVM\FluxVMException;
use SuperInstance\FluxVM\Loader;

final class VMTest extends TestCase
{
    public function testAdd(): void
    {
        // IAdd R8, R9, R10 where R9=2, R10=3 -> verify R8=5
        $asm = '
            IMov R9, 2
            IMov R10, 3
            IAdd R8, R9, R10
            Halt
        ';
        $bytecode = Assembler::assemble($asm);
        $vm = new FluxVM();
        $vm->load($bytecode);
        $vm->run();
        
        $this->assertEquals(5, $vm->getGP(8));
    }

    public function testMultiply(): void
    {
        // IMul R8, R9, R10 where R9=4, R10=7 -> verify R8=28
        $asm = '
            IMov R9, 4
            IMov R10, 7
            IMul R8, R9, R10
            Halt
        ';
        $bytecode = Assembler::assemble($asm);
        $vm = new FluxVM();
        $vm->load($bytecode);
        $vm->run();
        
        $this->assertEquals(28, $vm->getGP(8));
    }

    public function testConditionalJump(): void
    {
        // Test that JumpIf takes branch when register != 0
        // R8 = 1 initially, should jump and set R9 = 99
        // If not jumped, R9 stays 0
        $asm = '
            IMov R8, 1
            IMov R9, 0
            JumpIf R8, skip
            IMov R9, 0
            Halt
        skip:
            IMov R9, 99
            Halt
        ';
        $bytecode = Assembler::assemble($asm);
        $vm = new FluxVM();
        $vm->load($bytecode);
        $vm->run();
        
        $this->assertEquals(99, $vm->getGP(9));
    }

    public function testConditionalJumpNotTaken(): void
    {
        // Test that JumpIf does NOT take branch when register == 0
        $asm = '
            IMov R8, 0
            IMov R9, 0
            JumpIf R8, skip
            IMov R9, 42
            Halt
        skip:
            IMov R9, 99
            Halt
        ';
        $bytecode = Assembler::assemble($asm);
        $vm = new FluxVM();
        $vm->load($bytecode);
        $vm->run();
        
        $this->assertEquals(42, $vm->getGP(9));
    }

    public function testMemory(): void
    {
        // Test StackAlloc + Store32 + Load32
        $asm = '
            IMov R9, 42
            StackAlloc R8, 16
            Store32 R9, R8, 0
            Load32 R10, R8, 0
            Halt
        ';
        $bytecode = Assembler::assemble($asm);
        $vm = new FluxVM();
        $vm->load($bytecode);
        $vm->run();
        
        $this->assertEquals(42, $vm->getGP(10));
    }

    public function testDivisionByZero(): void
    {
        $asm = '
            IMov R9, 10
            IMov R10, 0
            IDiv R8, R9, R10
            Halt
        ';
        $bytecode = Assembler::assemble($asm);
        $vm = new FluxVM();
        $vm->load($bytecode);
        
        $this->expectException(FluxVMException::class);
        try {
            $vm->run();
        } catch (FluxVMException $e) {
            $this->assertEquals(FluxVMException::DIVISION_BY_ZERO, $e->getErrorCode());
            throw $e;
        }
    }

    public function testHalt(): void
    {
        $asm = '
            IMov R8, 123
            IMov R9, 456
            Halt
            IMov R8, 999
        ';
        $bytecode = Assembler::assemble($asm);
        $vm = new FluxVM();
        $vm->load($bytecode);
        $vm->run();
        
        // Should stop at Halt, R8 should still be 123
        $this->assertEquals(123, $vm->getGP(8));
        $this->assertEquals('halted', $vm->getState()->value);
    }

    public function testFactorial(): void
    {
        // Compute factorial(7) = 5040
        $asm = '
            IMov R0, 7
            IMov R1, 1
        loop:
            IMul R1, R1, R0
            IDec R0, 1
            JumpIfNot R0, loop
            Halt
        ';
        $bytecode = Assembler::assemble($asm);
        $vm = new FluxVM();
        $vm->load($bytecode);
        $vm->run();
        
        $this->assertEquals(5040, $vm->getGP(1));
    }

    public function testSubtract(): void
    {
        $asm = '
            IMov R9, 10
            IMov R10, 3
            ISub R8, R9, R10
            Halt
        ';
        $bytecode = Assembler::assemble($asm);
        $vm = new FluxVM();
        $vm->load($bytecode);
        $vm->run();
        
        $this->assertEquals(7, $vm->getGP(8));
    }

    public function testCompare(): void
    {
        $asm = '
            IMov R9, 5
            IMov R10, 5
            ICmpEq R8, R9, R10
            Halt
        ';
        $bytecode = Assembler::assemble($asm);
        $vm = new FluxVM();
        $vm->load($bytecode);
        $vm->run();
        
        $this->assertEquals(1, $vm->getGP(8));
    }

    public function testFloatOperations(): void
    {
        $asm = '
            IMov R9, 2
            IToF R9, R9, R0
            IMov R10, 3
            IToF R10, R10, R0
            FAdd F8, F9, F10
            FToI R8, F8, R0
            Halt
        ';
        $bytecode = Assembler::assemble($asm);
        $vm = new FluxVM();
        $vm->load($bytecode);
        $vm->run();
        
        $this->assertEquals(5, $vm->getGP(8));
    }

    public function testPushPop(): void
    {
        $asm = '
            IMov R9, 42
            Push R9
            IMov R9, 0
            Pop R9
            IMov R8, R9
            Halt
        ';
        $bytecode = Assembler::assemble($asm);
        $vm = new FluxVM();
        $vm->load($bytecode);
        $vm->run();
        
        $this->assertEquals(42, $vm->getGP(8));
    }

    public function testSwap(): void
    {
        $asm = '
            IMov R9, 10
            IMov R10, 20
            Swap R9, R10
            IMov R8, R9
            Halt
        ';
        $bytecode = Assembler::assemble($asm);
        $vm = new FluxVM();
        $vm->load($bytecode);
        $vm->run();
        
        $this->assertEquals(20, $vm->getGP(8));
    }

    public function testNop(): void
    {
        $asm = '
            IMov R8, 5
            Nop
            Nop
            Halt
        ';
        $bytecode = Assembler::assemble($asm);
        $vm = new FluxVM();
        $vm->load($bytecode);
        $vm->run();
        
        $this->assertEquals(5, $vm->getGP(8));
    }

    public function testLoadStore(): void
    {
        $asm = '
            IMov R9, 100
            Store8 R9, R0, 0
            Load8 R10, R0, 0
            IMov R8, R10
            Halt
        ';
        $bytecode = Assembler::assemble($asm);
        $vm = new FluxVM();
        $vm->load($bytecode);
        $vm->run();
        
        $this->assertEquals(100, $vm->getGP(8));
    }

    public function testMod(): void
    {
        $asm = '
            IMov R9, 17
            IMov R10, 5
            IMod R8, R9, R10
            Halt
        ';
        $bytecode = Assembler::assemble($asm);
        $vm = new FluxVM();
        $vm->load($bytecode);
        $vm->run();
        
        $this->assertEquals(2, $vm->getGP(8));
    }

    public function testAnd(): void
    {
        $asm = '
            IMov R9, 0b1100
            IMov R10, 0b1010
            IAnd R8, R9, R10
            Halt
        ';
        $bytecode = Assembler::assemble($asm);
        $vm = new FluxVM();
        $vm->load($bytecode);
        $vm->run();
        
        $this->assertEquals(0b1000, $vm->getGP(8));
    }

    public function testOr(): void
    {
        $asm = '
            IMov R9, 0b1100
            IMov R10, 0b1010
            IOr R8, R9, R10
            Halt
        ';
        $bytecode = Assembler::assemble($asm);
        $vm = new FluxVM();
        $vm->load($bytecode);
        $vm->run();
        
        $this->assertEquals(0b1110, $vm->getGP(8));
    }
}
