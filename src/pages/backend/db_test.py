from db import get_connection

conn = get_connection()
cursor = conn.cursor()
cursor.execute("SELECT * FROM tbl_centers")
print(cursor.fetchone())
conn.close()
