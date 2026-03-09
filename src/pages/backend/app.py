from flask import Flask
from flask_cors import CORS
from auth import auth_api

from centers         import centers_api
from subcenters      import subcenters_api
from volunteer_works import volunteer_works_api
from abhyasis               import abhyasis_api
from volunteer_work_abhyasi import vol_work_abhyasi_api
from attendance             import attendance_api
from reports                import reports_api
from sitting                import sitting_api
from events                 import events_api

app = Flask(__name__)
CORS(app)

app.register_blueprint(centers_api)
app.register_blueprint(subcenters_api)
app.register_blueprint(volunteer_works_api)
app.register_blueprint(abhyasis_api)
app.register_blueprint(vol_work_abhyasi_api)
app.register_blueprint(attendance_api)
app.register_blueprint(auth_api)
app.register_blueprint(reports_api)
app.register_blueprint(sitting_api)
app.register_blueprint(events_api)

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5002, debug=True)
