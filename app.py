from flask import Flask, render_template, request, redirect, session, flash
import mysql.connector
import os

app = Flask(__name__)
app.secret_key = os.environ.get("SECRET_KEY", "sager-secret-2025")


# ==========================================
# CONEXIÓN A RAILWAY (MySQL)
# ==========================================

def get_db():
    return mysql.connector.connect(
        host=os.environ.get("DB_HOST", "reseau.proxy.rlwy.net"),
        port=int(os.environ.get("DB_PORT", 25403)),
        user=os.environ.get("DB_USER", "root"),
        password=os.environ.get("DB_PASSWORD", "EaOMUSwcZDBQOhbWCDTnMudaBGwtyrRu"),
        database=os.environ.get("DB_NAME", "railway")
    )




# ==========================================
# INICIO — Lista todas las solicitudes hola
# ==========================================

@app.route("/")
def home():
    return redirect("/inicio")


@app.route("/inicio")
def inicio():

    if "id" not in session:
        return redirect("/login")

    return render_template("index.html")


# ==========================================
# SOLICITAR REPUESTO
# ==========================================

@app.route("/solicitar", methods=["GET", "POST"])
def solicitar():

    if "id" not in session:
        return redirect("/login")

    conn = get_db()
    cursor = conn.cursor(dictionary=True)

    # Obtener lista de vendedores
    cursor.execute("""
        SELECT nombre
        FROM usuarios
        ORDER BY nombre
    """)
    vendedores = cursor.fetchall()

    if request.method == "POST":

        try:
            vendedor = request.form.get("vendedor", "").strip()
            codigo = request.form.get("codigo", "").strip()
            cantidad = request.form.get("cantidad", "").strip()
            sede_origen = request.form.get("sede_origen", "").strip()
            sede_destino = request.form.get("sede_destino", "").strip()

            cursor.execute("""
                INSERT INTO solicitudes
                (vendedor, codigo, cantidad, sede_origen, sede_destino, estado)
                VALUES (%s,%s,%s,%s,%s,%s)
            """, (
                vendedor,
                codigo,
                cantidad,
                sede_origen,
                sede_destino,
                "Pendiente"
            ))

            conn.commit()

            flash("✅ Solicitud registrada correctamente", "success")

            cursor.close()
            conn.close()

            return redirect("/solicitar")

        except Exception as e:
            return f"ERROR AL INSERTAR: {e}"

    cursor.close()
    conn.close()

    return render_template(
        "solicitar.html",
        vendedores=vendedores
    )


# ==========================================
# CAMBIAR ESTADO
# ==========================================

@app.route("/estado/<int:id>/<nuevo_estado>")
def cambiar_estado(id, nuevo_estado):

    if "id" not in session:
        return redirect("/login")

    # Evitar estados inválidos
    estados_validos = ["Pendiente", "En Busqueda", "Listo", "No Disponible"]
    if nuevo_estado not in estados_validos:
        return "Estado no válido", 400

    try:
        conn   = get_db()
        cursor = conn.cursor()

        cursor.execute("""
            UPDATE solicitudes SET estado = %s WHERE id = %s
        """, (nuevo_estado, id))

        conn.commit()
        cursor.close()
        conn.close()

        return redirect("/pendientes")

    except Exception as e:
        return f"ERROR AL CAMBIAR ESTADO: {e}"


# ==========================================
# ELIMINAR SOLICITUD
# ==========================================

@app.route("/estado/<int:id>/Borrar")
def borrar(id):

    if "id" not in session:
        return redirect("/login")

    try:
        conn   = get_db()
        cursor = conn.cursor()

        cursor.execute("DELETE FROM solicitudes WHERE id = %s", (id,))

        conn.commit()
        cursor.close()
        conn.close()

        return redirect("/pendientes")

    except Exception as e:
        return f"ERROR AL BORRAR: {e}"


# ==========================================
# PENDIENTES
# ==========================================

@app.route("/pendientes")
def pendientes():

    if "id" not in session:
        return redirect("/login")

    try:
        conn   = get_db()
        cursor = conn.cursor(dictionary=True)

        cursor.execute("""
            SELECT * FROM solicitudes ORDER BY id DESC
        """)

        solicitudes = cursor.fetchall()
        cursor.close()
        conn.close()

        return render_template("pendientes.html", solicitudes=solicitudes)

    except Exception as e:
        return f"ERROR PENDIENTES: {e}"


# ==========================================
# CERRAR SESIÓN
# ==========================================

@app.route("/logout")
def logout():
    session.clear()
    return redirect("/login")


@app.route('/catalogo')
def catalogo():
    return render_template('catalogo.html')

# ==========================================
# ARRANQUE
# ==========================================

if __name__ == "__main__":
    port = int(os.environ.get("PORT", 5000))
    app.run(host="0.0.0.0", port=port, debug=True)