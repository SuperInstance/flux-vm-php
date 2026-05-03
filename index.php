<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuperInstance / flux-vm-php — FLUX ISA v3.0 Virtual Machine in Pure PHP</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0a0a0f; color: #e0e0e0; line-height: 1.6; }
        .container { max-width: 900px; margin: 0 auto; padding: 40px 20px; }
        header { text-align: center; margin-bottom: 60px; }
        .badge { display: inline-block; background: #1a1a2e; border: 1px solid #3a3a5e; padding: 4px 12px; border-radius: 20px; font-size: 12px; color: #888; margin-bottom: 16px; }
        h1 { font-size: 42px; font-weight: 700; margin-bottom: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .subtitle { font-size: 18px; color: #888; margin-bottom: 24px; }
        .github-btn { display: inline-block; background: #24292e; border: 1px solid #3a3a5e; color: #fff; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-size: 14px; }
        .github-btn:hover { background: #333; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 60px; }
        .stat { background: #1a1a2e; border: 1px solid #2a2a3e; border-radius: 8px; padding: 20px; text-align: center; }
        .stat-value { font-size: 28px; font-weight: 700; color: #667eea; }
        .stat-label { font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 1px; }
        section { margin-bottom: 48px; }
        h2 { font-size: 24px; margin-bottom: 16px; color: #fff; border-bottom: 1px solid #2a2a3e; padding-bottom: 8px; }
        pre { background: #12121a; border: 1px solid #2a2a3e; border-radius: 6px; padding: 16px; overflow-x: auto; font-size: 13px; margin-bottom: 24px; }
        code { color: #a8a8c8; }
        .kw { color: #c678dd; } .fn { color: #61afef; } .str { color: #98c379; } .num { color: #d19a66; }
        .features { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
        .feature { background: #12121a; border: 1px solid #2a2a3e; border-radius: 8px; padding: 16px; }
        .feature h3 { font-size: 14px; color: #667eea; margin-bottom: 6px; }
        .feature p { font-size: 13px; color: #888; }
        .links { display: flex; gap: 12px; flex-wrap: wrap; }
        .links a { background: #1a1a2e; border: 1px solid #3a3a5e; color: #667eea; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 13px; }
        .links a:hover { background: #242438; }
        .demo { background: #12121a; border: 1px solid #2a2a3e; border-radius: 8px; padding: 24px; margin-bottom: 24px; }
        .demo h3 { color: #667eea; margin-bottom: 12px; }
        footer { text-align: center; color: #444; font-size: 12px; margin-top: 60px; padding-top: 24px; border-top: 1px solid #1a1a2e; }
    </style>
</head>
<body>
<div class="container">

<header>
    <div class="badge">PHP 8.0+ · Pure PHP · No Extensions Required</div>
    <h1>flux-vm-php</h1>
    <p class="subtitle">FLUX ISA v3.0 Virtual Machine in Pure PHP</p>
    <a class="github-btn" href="https://github.com/SuperInstance/flux-vm-php">View on GitHub</a>
</header>

<div class="stats">
    <div class="stat"><div class="stat-value">100+</div><div class="stat-label">Opcodes</div></div>
    <div class="stat"><div class="stat-value">2,057</div><div class="stat-label">Lines (FluxVM)</div></div>
    <div class="stat"><div class="stat-value">16</div><div class="stat-label">GP Registers</div></div>
    <div class="stat"><div class="stat-value">5</div><div class="stat-label">ISA Formats</div></div>
</div>

<section>
    <h2>What is FLUX?</h2>
    <p>FLUX (Fleet Local eXecution Unit) is a fleet-native instruction set architecture with two layers:</p>
    <br>
    <ul style="margin-left: 24px; color: #888;">
        <li><strong style="color:#667eea">FLUX-C</strong> — 43 opcodes, stack-based, DAL-A certifiable safety layer</li>
        <li><strong style="color:#667eea">FLUX-X</strong> — 247 opcodes, register-based, general operations</li>
    </ul>
    <br>
    <p>This VM implements the full FLUX-X ISA with all core FLUX-C opcodes bridged through the gas-bounded execution model.</p>
</section>

<section>
    <h2>Quick Start</h2>
    <pre><code><span class="kw">composer</span> require superinstance/flux-vm

<span class="kw">require_once</span> <span class="str">'vendor/autoload.php'</span>;

<span class="kw">use</span> SuperInstance\FluxVM\FluxVM;

$vm = <span class="kw">new</span> <span class="fn">FluxVM</span>();
$vm->load($bytecode);
$result = $vm->run();
<span class="fn">echo</span> $vm->regs()[<span class="str">'R8'</span>];  <span style="color:#555">// R8 = return value</span></code></pre>
</section>

<section>
    <h2>Components</h2>
    <div class="features">
        <div class="feature">
            <h3>FluxVM</h3>
            <p>Core virtual machine: 16 GP + 16 FP + 16 VR registers, 64KB memory, decode-execute loop</p>
        </div>
        <div class="feature">
            <h3>Assembler</h3>
            <p>Text assembly to bytecode: labels, decimal/hex immediates, all opcode mnemonics</p>
        </div>
        <div class="feature">
            <h3>Disassembler</h3>
            <p>Bytecode to text assembly: decode any FLUX bytecode back to human-readable format</p>
        </div>
        <div class="feature">
            <h3>CLI</h3>
            <p>Command-line interface: <code>flux run</code>, <code>flux asm</code>, <code>flux dis</code>, <code>flux repl</code></p>
        </div>
        <div class="feature">
            <h3>PLATO Bridge</h3>
            <p>Integration with PLATO room server: submit tiles, query knowledge, fleet coordination</p>
        </div>
        <div class="feature">
            <h3>FLUX Compiler</h3>
            <p>Compile FLUX.MD structured markdown to executable bytecode (from FM's kit)</p>
        </div>
    </div>
</section>

<section>
    <h2>Interactive Demo</h2>
    <div class="demo">
        <h3>FLUX Sandbox — Browser VM</h3>
        <p>Try FLUX bytecode in your browser. Assembler, disassembler, and live execution.</p>
        <br>
        <a class="github-btn" href="https://cocapn.ai/flux-sandbox.html">Open FLUX Sandbox →</a>
    </div>
</section>

<section>
    <h2>Opcode Reference</h2>
    <pre><code><span class="kw">; Arithmetic</span>
IAdd R8, R9, R10    <span style="color:#555">// R8 = R9 + R10</span>
ISub R8, R9, R10    <span style="color:#555">// R8 = R9 - R10</span>
IMul R8, R9, R10    <span style="color:#555">// R8 = R9 * R10</span>
IDiv R8, R9, R10    <span style="color:#555">// R8 = R9 / R10</span>

<span class="kw">; Compare</span>
ICmpGt R8, R9, R10  <span style="color:#555">// R8 = R9 > R10 ? 1 : 0</span>
ICmpEq R8, R9, R10  <span style="color:#555">// R8 = R9 == R10 ? 1 : 0</span>

<span class="kw">; Control Flow</span>
Jump label          <span style="color:#555">// unconditional jump</span>
JumpIf R8, label    <span style="color:#555">// jump if R8 != 0</span>
Call func           <span style="color:#555">// call with return</span>
Halt                <span style="color:#555">// stop execution</span>

<span class="kw">; Memory</span>
StackAlloc R8, 16   <span style="color:#555">// allocate 16 bytes, addr in R8</span>
Store32 R8, R9      <span style="color:#555">// mem[R9] = R8 (32-bit)</span>
Load32 R8, R9       <span style="color:#555">// R8 = mem[R9] (32-bit)</span></code></pre>
</section>

<section>
    <h2>Live Widgets</h2>
    <div class="links">
        <a href="https://cocapn.ai/flux-sandbox.html">FLUX Sandbox</a>
        <a href="https://cocapn.ai/plato-browser.html">PLATO Browser</a>
        <a href="https://cocapn.ai/benchmark.html">Safe-TOPS/W Benchmark</a>
        <a href="https://cocapn.ai/constraint-playground.html">Constraint Playground</a>
    </div>
</section>

<footer>
    <p>Part of the <a href="https://github.com/SuperInstance" style="color:#667eea">SuperInstance</a> fleet · FLUX ISA v3.0</p>
</footer>

</div>
</body>
</html>