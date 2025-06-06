import waitress
from flask import Flask, request, jsonify

app = Flask(__name__)

# Global variable to store submitted data (for development purposes)
submitted_data = []

@app.route('/', methods=['POST', 'GET'])
def test():
    return "oui"

@app.route('/recieve_data', methods=['POST', 'GET'])
def recieve_data():
    if request.method == 'POST':
        # Handle POST request (form data)
        name = request.form.get('name')
        email = request.form.get('email')

        # Check if required parameters are missing
        if not name or not email:
            # Return a JSON response with a 400 status code
            return jsonify({
                'status': 'error',
                'message': 'Missing required parameters.'
            }), 400

        # Store the data in the global list
        submitted_data.append(name)
        submitted_data.append(email)

        # Process the data (e.g., save to database, log, etc.)
        print(f"Received data - Name: {name}, Email: {email}")

    elif request.method == 'GET':
        # Handle GET request (retrieve all submitted data)
        # Always return a JSON response
        name = submitted_data[0]
        email = submitted_data[1]

    return jsonify({
        'status': 'success',
        'message': 'Data received successfully!',
        'data': submitted_data
    })

if __name__ == '__main__':
    waitress.serve(app, listen='0.0.0.0:5001')