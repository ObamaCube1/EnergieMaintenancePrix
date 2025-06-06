import threading
import time
from collections import deque

# Agenda d'événements (timestamp, fonction)
agenda = deque()

def schedule():
    while True:
        current_time = time.time()
        print(str(current_time))
        # Vérifie les événements dans l'agenda
        while agenda and agenda[0][0] <= current_time:
            _, func = agenda.popleft()
            func()
        time.sleep(1)  # Attendre 1 seconde avant de revérifier

def add_event(timestamp, func):
    # Ajoute un événement à l'agenda en maintenant l'ordre
    index = 0
    while index < len(agenda) and agenda[index][0] < timestamp:
        index += 1
    agenda.insert(index, (timestamp, func))

# Exemple de fonction à appeler
def sample_function():
    print("Événement exécuté!")

# Ajouter un événement à l'agenda
add_event(time.time() + 5, sample_function)  # Exécute sample_function dans 5 secondes

# Démarrer le scheduleur dans un thread séparé
scheduler_thread = threading.Thread(target=schedule)
scheduler_thread.daemon = True
scheduler_thread.start()

# Le reste du programme peut continuer à s'exécuter
while True:
    print("!")
    time.sleep(10)
