from flask import Flask, render_template, request, redirect, abort
import mysql.connector
import os

app = Flask(__name__)
app.secret_key = os.environ.get("SECRET_KEY", "sager-secret-2025")


# ==========================================
# CONFIGURACIÓN DEL NEGOCIO
# ==========================================

NEGOCIO = {
    "nombre": "OBSCOOL",
    "lema": "Creatividad · Innovación · Estilo",
    # Número de WhatsApp con indicativo de país, sin + ni espacios
    "whatsapp": os.environ.get("WHATSAPP_NUMBER", "573227879835"),
}

# Para cambiar productos o precios, edita esta lista.
# "destacado": True -> aparece en la página de inicio.
PRODUCTOS = [
    # ---------- GORRAS ----------
    {"id": "g1", "nombre": "Gorra Plana Azul Royal", "categoria": "gorra",
     "descripcion": "Corte plano, ajuste firme y bordado frontal.",
     "precio": 55000, "imagen": "gorra-azul-royal.webp", "destacado": True},
    {"id": "g2", "nombre": "Gorra Clásica Negra", "categoria": "gorra",
     "descripcion": "Diseño curvo y ligero para uso diario.",
     "precio": 45000, "imagen": "gorra-negra-clasica.webp", "destacado": False},
    {"id": "g3", "nombre": "Gorra Plana Azul Marino", "categoria": "gorra",
     "descripcion": "Estilo urbano que combina con todo.",
     "precio": 40000, "imagen": "gorra-azul-marino.webp", "destacado": False},
    {"id": "g4", "nombre": "Gorra Plana Verde y Dorado", "categoria": "gorra",
     "descripcion": "Contraste de colores para destacar.",
     "precio": 55000, "imagen": "gorra-verde-dorado.webp", "destacado": False},
    {"id": "g5", "nombre": "Gorra Plana Negra y Roja", "categoria": "gorra",
     "descripcion": "Look deportivo con detalle bordado.",
     "precio": 55000, "imagen": "gorra-negra-roja.webp", "destacado": False},
    {"id": "g6", "nombre": "Gorra Crema y Verde", "categoria": "gorra",
     "descripcion": "Tono claro con visera contrastante.",
     "precio": 55000, "imagen": "gorra-crema-verde.webp", "destacado": False},
    # ---------- RELOJES ----------
    {"id": "r1", "nombre": "Reloj Elegante Dorado", "categoria": "reloj",
     "descripcion": "Diseño clásico para ocasiones especiales.",
     "precio": 120000, "imagen": "reloj-dorado.webp", "destacado": True},
    {"id": "r2", "nombre": "Reloj Minimalista Azul Hielo", "categoria": "reloj",
     "descripcion": "Esfera limpia, moderno y versátil.",
     "precio": 95000, "imagen": "reloj-azul-hielo.webp", "destacado": True},
    {"id": "r3", "nombre": "Reloj Deportivo Acero", "categoria": "reloj",
     "descripcion": "Robusto y con estilo para todos los días.",
     "precio": 110000, "imagen": "reloj-deportivo-acero.webp", "destacado": False},
    {"id": "r4", "nombre": "Reloj Esqueleto Oro Rosa", "categoria": "reloj",
     "descripcion": "Esfera esqueleto con correa de silicona.",
     "precio": 110000, "imagen": "reloj-esqueleto-oro-rosa.webp", "destacado": True},
    {"id": "r5", "nombre": "Reloj Esqueleto Naranja", "categoria": "reloj",
     "descripcion": "Caja negra y correa naranja llamativa.",
     "precio": 110000, "imagen": "reloj-esqueleto-naranja.webp", "destacado": False},
    {"id": "r6", "nombre": "Reloj Cronógrafo Titanio", "categoria": "reloj",
     "descripcion": "Acabado gris titanio de aire premium.",
     "precio": 110000, "imagen": "reloj-cronografo-titanio.webp", "destacado": False},
]


@app.template_filter("cop")
def formato_cop(valor):
    """45000 -> $45.000"""
    try:
        return "$" + f"{int(round(float(valor))):,}".replace(",", ".")
    except (TypeError, ValueError):
        return "$0"


@app.context_processor
def inyectar_globales():
    return {"negocio": NEGOCIO, "todos_productos": PRODUCTOS}


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
# PÁGINAS PÚBLICAS
# ==========================================

@app.route("/")
def home():
    return redirect("/inicio")


@app.route("/inicio")
def inicio():
    destacados = [p for p in PRODUCTOS if p["destacado"]]
    return render_template("index.html", destacados=destacados)


@app.route("/catalogo")
def catalogo():
    return render_template("catalogo.html", productos=PRODUCTOS)


# ==========================================
# COMPRAS (ADMINISTRACIÓN)
# Si defines la variable de entorno ADMIN_KEY, solo se puede entrar con
#   /compras?key=TU_CLAVE
# ==========================================

@app.route("/compras")
def compras():
    clave = os.environ.get("ADMIN_KEY")
    if clave and request.args.get("key") != clave:
        abort(404)

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
        return render_template("compras.html", compras=lista_compras)

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
    app.run(host="0.0.0.0", port=port, debug=False)
