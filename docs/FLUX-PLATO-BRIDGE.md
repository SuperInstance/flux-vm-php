# FLUX → PLATO Bridge

How PLATO drives FLUX execution for fleet coordination.

## Overview

PLATO is the memory layer. FLUX is the execution layer. The bridge connects them.

```
Agent → PLATO Room → Tile Write → MUD Server → FLUX VM → Execution
         ↑                                         ↓
         └────────────── Read/Query ←──────────────┘
```

## Tile → Instruction Flow

1. **Write Phase**: Agent writes a tile to a PLATO room
2. **Bake Phase**: `plato-hdc-bridge/bake.py` converts tile → FLUX bytecode
3. **Execute Phase**: FLUX VM executes bytecode
4. **Read Phase**: Results written back to PLATO as tiles

## PLATO as Instruction Cache

| Room Pattern | Content | FLUX Equivalent |
|---|---|---|
| `deckboss-ai` | `NAV.POSITION UPDATE` | FLUX opcodes for GPS |
| `fishinglog-ai` | `CATCH.RECORD weight=45` | FLUX data write |
| `businesslog-ai` | `INVOICE.SEND amount=120` | FLUX transaction |

## CLI Reference

```bash
# Read tiles from a room
curl http://localhost:8847/rooms/{room}/tiles?limit=50

# Write tile
curl -X POST http://localhost:8847/rooms/{room}/tiles \
  -H "Content-Type: application/json" \
  -d '{"content": "MESSAGE", "agent": "vessel-name"}'
```
