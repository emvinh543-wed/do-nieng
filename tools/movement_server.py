#!/usr/bin/env python3
"""
Simple WebSocket movement server for game animals.
Receives 'register' messages from clients and sends periodic 'animal_update' messages with positions.

Protocol (JSON):
- Client -> Server: {"type":"register","id":"a_...","x":0,"y":0,"z":0,"map":"park"}
- Server -> Client: {"type":"animal_update","animals":[{"id":"a_...","x":1.2,"y":0.0,"z":3.4,"rotation":0.3}, ...]}

Run:
    pip install websockets
    python tools/movement_server.py

This is intentionally minimal and runs locally on ws://localhost:8765
"""
import asyncio
import json
import math
import random
import time
from collections import defaultdict

import websockets
from aiohttp import web

PORT = 8765
EFFECTS_PORT = 8766
TICK = 0.08  # 80ms per update

# animal state: id -> dict
animals = {}
# connected movement clients
movement_clients = set()
# connected effects clients
effects_clients = set()

# simple seeded per-id pseudo-random motion generator state
def init_animal_state(aid, x, y, z):
    seed = abs(hash(aid)) % (2**31)
    rnd = random.Random(seed)
    return {
        'id': aid,
        'x': x,
        'y': y,
        'z': z,
        'phase': rnd.random() * math.pi * 2,
        'speed': 0.02 + rnd.random() * 0.06,
        'angle': rnd.random() * math.pi * 2,
        'amp': 0.2 + rnd.random() * 0.8,
        'last_update': time.time()
    }

async def register_animal(msg):
    aid = msg.get('id')
    if not aid:
        return
    if aid not in animals:
        animals[aid] = init_animal_state(aid, msg.get('x',0), msg.get('y',0), msg.get('z',0))

async def unregister_animal(aid):
    if aid in animals:
        del animals[aid]

async def handle_movement_ws(ws, path):
    movement_clients.add(ws)
    try:
        async for raw in ws:
            try:
                msg = json.loads(raw)
            except Exception:
                continue
            t = msg.get('type')
            if t == 'register':
                await register_animal(msg)
            elif t == 'unregister':
                await unregister_animal(msg.get('id'))
    finally:
        movement_clients.remove(ws)

async def handle_effects_ws(ws, path):
    effects_clients.add(ws)
    try:
        async for raw in ws:
            # effects WS is primarily broadcast-only; clients may subscribe
            try:
                msg = json.loads(raw)
                # accept subscribe messages if needed
            except Exception:
                continue
    finally:
        effects_clients.remove(ws)

async def tick_loop():
    while True:
        if animals:
            now = time.time()
            out = {'type':'animal_update', 'animals':[]}
            for aid, st in list(animals.items()):
                dt = now - st['last_update']
                st['last_update'] = now
                st['angle'] += (random.random()-0.5) * 0.4 * dt
                dx = math.cos(st['angle']) * st['speed'] * (1.0 + math.sin(now + st['phase'])*0.3)
                dz = math.sin(st['angle']) * st['speed'] * (1.0 + math.cos(now + st['phase'])*0.3)
                st['x'] += dx * 40
                st['z'] += dz * 40
                st['y'] = max(0, math.sin(now * 2 + st['phase']) * st['amp'])
                rot = st['angle'] + math.pi/2
                out['animals'].append({'id': aid, 'x': st['x'], 'y': st['y'], 'z': st['z'], 'rotation': rot})

            data = json.dumps(out)
            if movement_clients:
                await asyncio.gather(*[c.send(data) for c in list(movement_clients) if c.open], return_exceptions=True)
        await asyncio.sleep(TICK)

async def broadcast_effect(msg: dict):
    data = json.dumps(msg)
    if effects_clients:
        await asyncio.gather(*[c.send(data) for c in list(effects_clients) if c.open], return_exceptions=True)

### HTTP endpoints for triggering effects (runs on EFFECTS_PORT)
async def http_eat(request):
    try:
        payload = await request.json()
    except Exception:
        payload = {}
    pos = payload.get('position') or { 'x': payload.get('x',0), 'y': payload.get('y',0), 'z': payload.get('z',0) }
    msg = { 'type':'eat', 'position': pos }
    asyncio.create_task(broadcast_effect(msg))
    return web.json_response({'status':'ok', 'sent': msg})

async def http_weather(request):
    try:
        payload = await request.json()
    except Exception:
        payload = {}
    weather = payload.get('weather') or payload.get('state') or 'clear'
    intensity = float(payload.get('intensity', 0.6)) if payload.get('intensity') is not None else 0.6
    msg = { 'type':'weather', 'weather': weather, 'intensity': intensity }
    asyncio.create_task(broadcast_effect(msg))
    return web.json_response({'status':'ok', 'sent': msg})

async def start_effects_http():
    app = web.Application()
    app.router.add_post('/eat', http_eat)
    app.router.add_post('/weather', http_weather)
    runner = web.AppRunner(app)
    await runner.setup()
    site = web.TCPSite(runner, '0.0.0.0', EFFECTS_PORT)
    await site.start()
    print(f'Effects HTTP endpoints running on http://localhost:{EFFECTS_PORT}')

async def main():
    mv_server = await websockets.serve(handle_movement_ws, '0.0.0.0', PORT)
    eff_server = await websockets.serve(handle_effects_ws, '0.0.0.0', EFFECTS_PORT)
    print(f'Movement WS running on ws://localhost:{PORT}\nEffects WS running on ws://localhost:{EFFECTS_PORT}')
    # start HTTP endpoints for effects
    await start_effects_http()
    await tick_loop()

if __name__ == '__main__':
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        print('Server stopped')
