
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
# CAMBIAR ESTADO
# ==========================================



# ==========================================
# ELIMINAR SOLICITUD
# ==========================================


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
# COMPRAS DE LOS USUARIOS
# ==========================================

@app.route("/compras")
def compras():
    conn = None
    cursor = None

    try:
        conn = get_db()
        cursor = conn.cursor(dictionary=True)

        cursor.execute("""
            SELECT
                id,
                usuario,
                producto,
                codigo,
                cantidad,
                precio_unitario,
                cantidad * precio_unitario AS valor_total,
                fecha_compra AS fecha,
                estado
            FROM compras
            ORDER BY fecha_compra DESC
        """)

        lista_compras = cursor.fetchall()

        return render_template(
            "compras.html",
            compras=lista_compras
        )

    except Exception:
        app.logger.exception("Error al consultar compras")
        return "Error al consultar las compras.", 500

    finally:
        if cursor is not None:
            cursor.close()
        if conn is not None and conn.is_connected():
            conn.close()





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
