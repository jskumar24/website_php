"""
auth.py  -  TS-12 Login API

POST /api/auth/login   { "srcm_id": "INXXXXX" }

Access rules:
  - Allowed to log in : Preceptor, ZC, CC, DC, NC
  - role_level "full" : NC / ZC / CC / DC  (full site access)
  - role_level "preceptor": Preceptor only (limited access)
  - can_delete        : ZC and DC only
"""

from flask import Blueprint, jsonify, request
from db import get_connection

auth_api = Blueprint("auth_api", __name__)

# All roles allowed to log in  (must match LOWER(volunteer_name) in DB)
ALLOWED_ROLES = {"preceptor", "zc", "cc", "dc", "nc"}

# These roles get full site access
FULL_ROLES    = {"zc", "cc", "dc", "nc"}

# Only these roles can use delete buttons
DELETE_ROLES  = {"zc", "dc"}

# Human-readable labels
LABEL_MAP     = {"preceptor": "Preceptor", "zc": "ZC", "cc": "CC", "dc": "DC", "nc": "NC"}


def _get_vol_pk(conn):
    """Auto-detect the PK column name of tbl_volunteer_work."""
    cur = conn.cursor(dictionary=True)
    cur.execute("SHOW KEYS FROM tbl_volunteer_work WHERE Key_name = 'PRIMARY'")
    row = cur.fetchone()
    cur.close()
    return row["Column_name"] if row else "vol_id"


@auth_api.route("/api/auth/login", methods=["POST"])
def login():
    data    = request.get_json(force=True) or {}
    srcm_id = str(data.get("srcm_id", "")).strip()

    if not srcm_id:
        return jsonify({"error": "srcm_id is required"}), 400

    try:
        conn   = get_connection()
        cur    = conn.cursor(dictionary=True)
        vol_pk = _get_vol_pk(conn)

        # 1. Find the abhyasi by SRCM ID (case-insensitive, trims spaces)
        cur.execute("""
            SELECT abhyasi_id,
                   TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) AS full_name,
                   srcm_id
            FROM tbl_abhyasis
            WHERE UPPER(TRIM(srcm_id)) = UPPER(TRIM(%s))
            LIMIT 1
        """, (srcm_id,))
        abhyasi = cur.fetchone()

        if not abhyasi:
            cur.close(); conn.close()
            return jsonify({
                "error": "SRCM ID not found. Please check your ID or contact your coordinator."
            }), 401

        # 2. Fetch all volunteer roles assigned to this abhyasi
        #    LOWER + TRIM ensures "CC", " CC ", "cc" all match correctly
        cur.execute(f"""
            SELECT LOWER(TRIM(vw.volunteer_name)) AS role_name
            FROM tbl_volunteer_work_abhyasi va
            JOIN tbl_volunteer_work vw ON vw.{vol_pk} = va.vol_id
            WHERE va.abhyasi_id = %s
        """, (abhyasi["abhyasi_id"],))

        all_roles  = [r["role_name"] for r in cur.fetchall()]
        user_roles = [r for r in all_roles if r in ALLOWED_ROLES]

        cur.close()
        conn.close()

        if not user_roles:
            return jsonify({
                "error": "Access denied. Only Preceptors, ZC, CC, DC and NC may log in."
            }), 403

        # 3. Determine access level from roles
        #    NC / CC / ZC / DC  →  "full" access
        #    Preceptor only     →  "preceptor" (limited) access
        has_full   = any(r in FULL_ROLES   for r in user_roles)
        can_delete = any(r in DELETE_ROLES for r in user_roles)
        role_level = "full" if has_full else "preceptor"

        display = [LABEL_MAP[r] for r in user_roles if r in LABEL_MAP]

        return jsonify({
            "success":    True,
            "srcm_id":    abhyasi["srcm_id"],
            "name":       abhyasi["full_name"],
            "roles":      display,       # e.g. ["CC", "NC"] or ["Preceptor"]
            "role_level": role_level,    # "full" | "preceptor"
            "can_delete": can_delete     # True only for ZC / DC
        })

    except Exception as e:
        return jsonify({"error": f"Server error: {str(e)}"}), 500
