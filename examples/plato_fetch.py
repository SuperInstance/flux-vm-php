import requests, json, sys

def fetch_plato_tiles(room="fleet_communication", url="http://127.0.0.1:8847"):
    """Fetch tiles from a PLATO room and save to JSON."""
    try:
        resp = requests.get(f"{url}/room/{room}", timeout=5)
        resp.raise_for_status()
        data = resp.json()
        
        # Handle dict response (tiles are in a nested structure)
        if isinstance(data, dict):
            tiles = data.get("tiles", list(data.values())[0] if data else [])
        else:
            tiles = data
        
        print(f"Fetched {len(tiles)} tiles from room: {room}")
        
        out_path = f"examples/plato_tiles_{room}.json"
        with open(out_path, "w") as f:
            json.dump(tiles, f, indent=2)
        print(f"Saved to {out_path}")
        return tiles
    except Exception as e:
        print(f"Error: {e}", file=sys.stderr)
        return []

if __name__ == "__main__":
    tiles = fetch_plato_tiles()
    print(f"Done. {len(tiles)} tiles fetched.")
