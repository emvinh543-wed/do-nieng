#!/usr/bin/env python3
"""
Simple Effects WebSocket Server
Provides weather broadcasts to connected clients.

Run:
  pip install websockets
  python tools/effects_server.py

Listens on ws://localhost:8766 and periodically updates weather state.
Clients receive messages like: {"type":"weather","payload":{"state":"rain","intensity":0.6}}
"""
import asyncio
import json
import random
import time
import websockets

CLIENTS = set()
WEATHER = {"state": "clear", "intensity": 0.0, "updated": time.time()}

async def broadcast(message: dict):
    if not CLIENTS:
        return
    data = json.dumps(message)
    await asyncio.wait([ws.send(data) for ws in CLIENTS])

async def weather_changer():
    """Background task that randomly changes weather state."""
    global WEATHER
    while True:
        await asyncio.sleep(8)
        # small chance to change
        r = random.random()
        prev = WEATHER['state']
        if prev == 'clear':
            if r > 0.78:
                WEATHER = {"state": "rain", "intensity": round(random.uniform(0.35, 0.85), 2), "updated": time.time()}
            elif r > 0.94:
                WEATHER = {"state": "storm", "intensity": round(random.uniform(0.6, 1.0), 2), "updated": time.time()}
        elif prev == 'rain':
            if r > 0.8:
                WEATHER = {"state": "clear", "intensity": 0.0, "updated": time.time()}
            elif r > 0.9:
                WEATHER = {"state": "storm", "intensity": round(random.uniform(0.6, 1.0), 2), "updated": time.time()}
        elif prev == 'storm':
            if r > 0.6:
                WEATHER = {"state": "rain", "intensity": round(random.uniform(0.2, 0.6), 2), "updated": time.time()}
            elif r > 0.85:
                WEATHER = {"state": "clear", "intensity": 0.0, "updated": time.time()}

        await broadcast({"type": "weather", "payload": WEATHER})

async def handler(ws, path):
    # register
    CLIENTS.add(ws)
    try:
        # send current weather immediately
        await ws.send(json.dumps({"type": "weather", "payload": WEATHER}))
        async for msg in ws:
            try:
                data = json.loads(msg)
            except Exception:
                continue
            # allow clients to request current weather or set (trusted)
            if data.get('type') == 'get_weather':
                await ws.send(json.dumps({"type": "weather", "payload": WEATHER}))
            elif data.get('type') == 'set_weather' and isinstance(data.get('payload'), dict):
                # trusted setter (no auth) — useful for dev/testing
                p = data['payload']
                st = p.get('state')
                it = float(p.get('intensity', 0)) if p.get('intensity') is not None else 0.0
                if st in ('clear', 'rain', 'storm'):
                    WEATHER = {"state": st, "intensity": max(0.0, min(1.0, it)), "updated": time.time()}
                    await broadcast({"type": "weather", "payload": WEATHER})
    finally:
        CLIENTS.remove(ws)

def main():
    loop = asyncio.get_event_loop()
    start_server = websockets.serve(handler, '0.0.0.0', 8766)
    loop.run_until_complete(start_server)
    # start background weather changer
    loop.create_task(weather_changer())
    print('Effects server running on ws://localhost:8766')
    try:
        loop.run_forever()
    except KeyboardInterrupt:
        print('Shutting down')

if __name__ == '__main__':
    main()
