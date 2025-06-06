import heapq
import json
import os
import threading
import time
import uuid
from flask import Flask, request, jsonify

app = Flask(__name__)

AGENDA_FILE = "agenda.json"

agenda = []
agenda_lock = threading.Lock()
new_event = threading.Event()
events_map = {}  # Permet d'annuler les événements

#========= Agenda =========

def schedule():
    while True:
        with agenda_lock:
            now = time.time()
            modified = False  # Pour savoir si on a modifié l'agenda

            while agenda and (agenda[0][0] <= now or not events_map.get(agenda[0][1])):
                _, event_id, func, args = heapq.heappop(agenda)
                if event_id in events_map:
                    del events_map[event_id]
                    try:
                        func(*args)
                    except Exception as e:
                        print(f"Erreur dans {func.__name__}: {e}")
                modified = True

        if modified:
            save_agenda_to_file()  # Enregistre après avoir modifié

        now = time.time()
        delay = max(0, agenda[0][0] - now) if agenda else None

        if delay is None:
            new_event.wait()
        else:
            new_event.wait(timeout=delay)

        new_event.clear()

def load_agenda_from_file():
    if os.path.exists(AGENDA_FILE):
        with open(AGENDA_FILE, "r") as f:
            try:
                data = json.load(f)
                for event in data:
                    schedule_event_from_dict(event)
            except json.JSONDecodeError as e:
                print(f"Erreur de lecture de {AGENDA_FILE}: {e}")

def save_agenda_to_file():
    with agenda_lock:
        events = [
            {
                "event_id": eid,
                "timestamp": ts,
                "function": func.__name__,
                "args": args
            }
            for ts, eid, func, args in agenda if events_map.get(eid)
        ]
        with open(AGENDA_FILE, "w") as f:
            json.dump(events, f, indent=2)

def schedule_event_from_dict(event):
    timestamp = event.get("timestamp")
    func_name = event.get("function")
    args = event.get("args", [])
    event_id = event.get("event_id", str(uuid.uuid4()))

    func = function_map.get(func_name)
    if not func:
        print(f"Fonction inconnue : {func_name}")
        return

    with agenda_lock:
        heapq.heappush(agenda, (timestamp, event_id, func, args))
        events_map[event_id] = True
    new_event.set()

def schedule_event(timestamp, func, args=None):
    if args is None:
        args = []
    with agenda_lock:
        event_id = str(uuid.uuid4())
        heapq.heappush(agenda, (timestamp, event_id, func, args))
        events_map[event_id] = True
    new_event.set()

def cancel_event(event_id):
    with agenda_lock:
        if event_id in events_map:
            del events_map[event_id]
    new_event.set()

def replace_agenda(new_events):
    with agenda_lock:
        agenda.clear()
        events_map.clear()
        for event in new_events:
            timestamp = event.get("timestamp")
            func_name = event.get("function")
            args = event.get("args", [])
            func = function_map.get(func_name)
            if func is not None:
                event_id = str(uuid.uuid4())
                heapq.heappush(agenda, (timestamp, event_id, func, args))
                events_map[event_id] = True

    new_event.set()

#========= Flask =========

@app.route("/agenda", methods=["GET"])
def get_agenda():
    with agenda_lock:
        events = [
            {
                "event_id": eid,
                "timestamp": ts,
                "function": func.__name__,
                "args": args
            }
            for ts, eid, func, args in agenda if events_map.get(eid)
        ]
    return jsonify(events)

@app.route("/agenda", methods=["POST"])
def add_event():
    data = request.get_json()
    if not isinstance(data, dict):
        return jsonify({"error": "Données attendues: {timestamp, function, args}"}), 400
    try:
        timestamp = data["timestamp"]
        func_name = data["function"]
        args = data.get("args", [])
        event_id = str(uuid.uuid4())
        schedule_event_from_dict({
            "event_id": event_id,
            "timestamp": timestamp,
            "function": func_name,
            "args": args
        })
        save_agenda_to_file()
        return jsonify({"status": "Ajouté", "event_id": event_id})
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route("/agenda", methods=["PUT"])
def replace_entire_agenda():
    data = request.get_json()
    print("Données reçues :", data)
    if not isinstance(data, list):
        return jsonify({"error": "Une liste d'événements est attendue"}), 400
    try:
        replace_agenda(data)
        save_agenda_to_file()
        return jsonify({"status": "Agenda remplacé", "event_count": len(data)})
    except Exception as e:
        print("Erreur dans replace_agenda:", e)
        return jsonify({"error": str(e)}), 500


@app.route("/agenda/<event_id>", methods=["PUT"])
def update_event(event_id):
    data = request.get_json()
    if not isinstance(data, dict):
        return jsonify({"error": "Données attendues: {timestamp, function, args}"}), 400

    with agenda_lock:
        # Supprimer l'ancien (si actif)
        events_map[event_id] = False
        # Ajouter le nouveau
        try:
            timestamp = data["timestamp"]
            func_name = data["function"]
            args = data.get("args", [])
            func = function_map.get(func_name)
            if not func:
                return jsonify({"error": f"Fonction inconnue : {func_name}"}), 400
            heapq.heappush(agenda, (timestamp, event_id, func, args))
            events_map[event_id] = True
            save_agenda_to_file()
            return jsonify({"status": "Mis à jour", "event_id": event_id})
        except Exception as e:
            return jsonify({"error": str(e)}), 500

@app.route("/agenda/<event_id>", methods=["DELETE"])
def delete_event(event_id):
    with agenda_lock:
        if event_id in events_map:
            events_map[event_id] = False
            save_agenda_to_file()
            return jsonify({"status": "Supprimé"})
        else:
            return jsonify({"error": "Événement non trouvé"}), 404

#========= Fonctions metier =========

def sample_function(x):
    print(f"sample_function({x}) exécutée à {time.ctime()}")

def other_function(msg, val):
    print(f"other_function({msg}, {val}) exécutée à {time.ctime()}")

function_map = {
    "sample_function": sample_function,
    "other_function": other_function,
}

#========= Main =========
load_agenda_from_file()
threading.Thread(target=schedule, daemon=True).start()

if __name__ == "__main__":
    app.run(host="0.0.0.0", debug=True)
