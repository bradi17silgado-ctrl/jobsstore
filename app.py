
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
# INICIO
# ==========================================

@app.route("/")
def home():
    return redirect("/inicio")


@app.route("/inicio")
def inicio():
    return render_template("index.html")


# ==========================================
# SOLICITAR REPUESTO
# ==========================================

@app.route("/solicitar", methods=["GET", "POST"])
def solicitar():

    conn = None
    cursor = None

    try:
        conn = get_db()
        cursor = conn.cursor(dictionary=True)

        cursor.execute("""
            SELECT nombre
            FROM usuarios
            ORDER BY nombre
        """)

        vendedores = cursor.fetchall()

        if request.method == "POST":

            vendedor = request.form.get("vendedor", "").strip()
            codigo = request.form.get("codigo", "").strip()
            cantidad = request.form.get("cantidad", "").strip()
            sede_origen = request.form.get("sede_origen", "").strip()
            sede_destino = request.form.get("sede_destino", "").strip()

            if not all([
                vendedor,
                codigo,
                cantidad,
                sede_origen,
                sede_destino
            ]):
                flash("Por favor, completa todos los campos.", "warning")
                return render_template(
                    "solicitar.html",
                    vendedores=vendedores
                )

            cursor.execute("""
                INSERT INTO solicitudes
                (vendedor, codigo, cantidad, sede_origen, sede_destino, estado)
                VALUES (%s, %s, %s, %s, %s, %s)
            """, (
                vendedor,
                codigo,
                cantidad,
                sede_origen,
                sede_destino,
                "Pendiente"
            ))

            conn.commit()

            flash("Solicitud registrada correctamente.", "success")
            return redirect("/solicitar")

        return render_template(
            "solicitar.html",
            vendedores=vendedores
        )

    except Exception as e:
        app.logger.exception("Error en solicitar")
        return f"Error al procesar la solicitud: {e}", 500

    finally:
        if cursor is not None:
            cursor.close()
        if conn is not None and conn.is_connected():
            conn.close()


# ==========================================
# CAMBIAR ESTADO
# ==========================================

@app.route("/estado/<int:id>/<nuevo_estado>")
def cambiar_estado(id, nuevo_estado):

    estados_validos = [
        "Pendiente",
        "En Busqueda",
        "Listo",
        "No Disponible"
    ]

    if nuevo_estado not in estados_validos:
        return "Estado no válido", 400

    conn = None
    cursor = None

    try:
        conn = get_db()
        cursor = conn.cursor()

        cursor.execute("""
            UPDATE solicitudes
            SET estado = %s
            WHERE id = %s
        """, (nuevo_estado, id))

        conn.commit()

        return redirect("/pendientes")

    except Exception as e:
        app.logger.exception("Error al cambiar estado")
        return f"Error al cambiar estado: {e}", 500

    finally:
        if cursor is not None:
            cursor.close()
        if conn is not None and conn.is_connected():
            conn.close()


# ==========================================
# ELIMINAR SOLICITUD
# ==========================================

@app.route("/estado/<int:id>/Borrar", methods=["POST"])
def borrar(id):

    conn = None
    cursor = None

    try:
        conn = get_db()
        cursor = conn.cursor()

        cursor.execute(
            "DELETE FROM solicitudes WHERE id = %s",
            (id,)
        )

        conn.commit()

        flash("Solicitud eliminada correctamente.", "success")
        return redirect("/pendientes")

    except Exception as e:
        app.logger.exception("Error al borrar solicitud")
        return f"Error al borrar la solicitud: {e}", 500

    finally:
        if cursor is not None:
            cursor.close()
        if conn is not None and conn.is_connected():
            conn.close()


# ==========================================
# PENDIENTES
# ==========================================

@app.route("/pendientes")
def pendientes():

    conn = None
    cursor = None

    try:
        conn = get_db()
        cursor = conn.cursor(dictionary=True)

        cursor.execute("""
            SELECT *
            FROM solicitudes
            ORDER BY id DESC
        """)

        solicitudes = cursor.fetchall()

        return render_template(
            "pendientes.html",
            solicitudes=solicitudes
        )

    except Exception as e:
        app.logger.exception("Error al consultar pendientes")
        return f"Error al consultar pendientes: {e}", 500

    finally:
        if cursor is not None:
            cursor.close()
        if conn is not None and conn.is_connected():
            conn.close()


# ==========================================
# CATÁLOGO
# ==========================================

@app.route("/catalogo")
def catalogo():
    return render_template("catalogo.html")


# ==========================================
# CERRAR SESIÓN
# ==========================================




# ==========================================
# ARRANQUE
# ==========================================

if __name__ == "__main__":
    port = int(os.environ.get("PORT", 5000))
    app.run(
        host="0.0.0.0",
        port=port,
        debug=False
    )
