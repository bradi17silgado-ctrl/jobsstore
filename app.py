from flask import Flask, render_template, request, redirect, abort, jsonify
from urllib.parse import urlencode
import hashlib
import secrets
import time
import mysql.connector
import os
import requests

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
     "precio": 55000, "imagen": "gorras/gorra-azul-royal.webp", "destacado": True},
    {"id": "g2", "nombre": "Gorra Clásica Negra", "categoria": "gorra",
     "descripcion": "Diseño curvo y ligero para uso diario.",
     "precio": 45000, "imagen": "gorras/gorra-negra-clasica.webp", "destacado": False},
    {"id": "g3", "nombre": "Gorra Plana Azul Marino", "categoria": "gorra",
     "descripcion": "Estilo urbano que combina con todo.",
     "precio": 40000, "imagen": "gorras/gorra-azul-marino.webp", "destacado": False},
    {"id": "g4", "nombre": "Gorra Plana Verde y Dorado", "categoria": "gorra",
     "descripcion": "Contraste de colores para destacar.",
     "precio": 55000, "imagen": "gorras/gorra-verde-dorado.webp", "destacado": False},
    {"id": "g5", "nombre": "Gorra Plana Negra y Roja", "categoria": "gorra",
     "descripcion": "Look deportivo con detalle bordado.",
     "precio": 55000, "imagen": "gorras/gorra-negra-roja.webp", "destacado": False},
    {"id": "g6", "nombre": "Gorra Crema y Verde", "categoria": "gorra",
     "descripcion": "Tono claro con visera contrastante.",
     "precio": 55000, "imagen": "gorras/gorra-crema-verde.webp", "destacado": False},
    # ---------- RELOJES ----------
    {"id": "r1", "nombre": "Reloj Elegante Dorado", "categoria": "reloj",
     "descripcion": "Diseño clásico para ocasiones especiales.",
     "precio": 120000, "imagen": "relojes/reloj-dorado.webp", "destacado": True},
    {"id": "r2", "nombre": "Reloj Minimalista Azul Hielo", "categoria": "reloj",
     "descripcion": "Esfera limpia, moderno y versátil.",
     "precio": 95000, "imagen": "relojes/reloj-azul-hielo.webp", "destacado": True},
    {"id": "r3", "nombre": "Reloj Deportivo Acero", "categoria": "reloj",
     "descripcion": "Robusto y con estilo para todos los días.",
     "precio": 110000, "imagen": "relojes/reloj-deportivo-acero.webp", "destacado": False},
    {"id": "r4", "nombre": "Reloj Esqueleto Oro Rosa", "categoria": "reloj",
     "descripcion": "Esfera esqueleto con correa de silicona.",
     "precio": 110000, "imagen": "relojes/reloj-esqueleto-oro-rosa.webp", "destacado": True},
    {"id": "r5", "nombre": "Reloj Esqueleto Naranja", "categoria": "reloj",
     "descripcion": "Caja negra y correa naranja llamativa.",
     "precio": 110000, "imagen": "relojes/reloj-esqueleto-naranja.webp", "destacado": False},
    {"id": "r6", "nombre": "Reloj Cronógrafo Titanio", "categoria": "reloj",
     "descripcion": "Acabado gris titanio de aire premium.",
     "precio": 110000, "imagen": "relojes/reloj-cronografo-titanio.webp", "destacado": False},
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
    return {"negocio": NEGOCIO, "todos_productos": PRODUCTOS, "pse_activo": pse_configurado()}


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
# PAGO CON PSE (Wompi)
# Variables de entorno necesarias (las entrega Wompi en su panel):
#   WOMPI_PUBLIC_KEY        llave pública   (pub_test_... / pub_prod_...)
#   WOMPI_INTEGRITY_SECRET  secreto de integridad (firma del checkout)
#   WOMPI_EVENTS_SECRET     secreto de eventos (valida el webhook)
#   WOMPI_ENV               "sandbox" (por defecto) o "production"
# Sin WOMPI_PUBLIC_KEY y WOMPI_INTEGRITY_SECRET el botón PSE no se muestra.
# En el panel de Wompi, configura la URL de eventos:  https://TU-DOMINIO/pago/webhook
# El cliente elige PSE en el checkout de Wompi (debe estar habilitado en tu comercio).
# ==========================================

WOMPI_CHECKOUT = "https://checkout.wompi.co/p/"
ESTADOS_PAGO = {
    "APPROVED": "PAGADO",
    "DECLINED": "RECHAZADO",
    "VOIDED": "ANULADO",
    "ERROR": "ERROR",
    "PENDING": "PENDIENTE",
}


def pse_configurado():
    return bool(os.environ.get("WOMPI_PUBLIC_KEY") and os.environ.get("WOMPI_INTEGRITY_SECRET"))


def wompi_api():
    if os.environ.get("WOMPI_ENV", "sandbox").lower() == "production":
        return "https://production.wompi.co/v1"
    return "https://sandbox.wompi.co/v1"


def sha256(texto):
    return hashlib.sha256(texto.encode("utf-8")).hexdigest()


@app.route("/pago/pse", methods=["POST"])
def pago_pse():
    """Recibe el carrito, recalcula el total con los precios del servidor,
    registra la compra como PENDIENTE y devuelve la URL de pago de Wompi."""
    if not pse_configurado():
        return jsonify(error="El pago con PSE no está disponible por ahora."), 503

    datos = request.get_json(silent=True) or {}
    items = datos.get("items") or {}
    nombre = str(datos.get("nombre") or "").strip()[:60] or "Cliente web"
    ciudad = str(datos.get("ciudad") or "").strip()[:80]

    catalogo = {p["id"]: p for p in PRODUCTOS}
    lineas = []
    for pid, cant in items.items():
        try:
            cant = int(cant)
        except (TypeError, ValueError):
            continue
        if pid in catalogo and 0 < cant <= 99:
            lineas.append((catalogo[pid], cant))
    if not lineas:
        return jsonify(error="Tu pedido está vacío."), 400

    total = sum(p["precio"] * c for p, c in lineas)
    referencia = "OBS-%d-%s" % (int(time.time()), secrets.token_hex(3).upper())

    conn = cursor = None
    try:
        conn = get_db()
        cursor = conn.cursor()
        # La referencia va en "usuario" para poder cruzar el pago con la compra.
        usuario = "%s | %s" % (nombre, referencia)
        for p, c in lineas:
            cursor.execute(
                "INSERT INTO compras (usuario, producto, codigo, cantidad, precio_unitario, estado) "
                "VALUES (%s, %s, %s, %s, %s, %s)",
                (usuario, p["nombre"], p["id"], c, p["precio"], "PENDIENTE"),
            )
        conn.commit()
    except Exception:
        app.logger.exception("No se pudo registrar la compra")
        return jsonify(error="No pudimos registrar tu pedido. Intenta de nuevo."), 500
    finally:
        if cursor is not None:
            cursor.close()
        if conn is not None and conn.is_connected():
            conn.close()

    centavos = int(total) * 100
    params = {
        "public-key": os.environ["WOMPI_PUBLIC_KEY"],
        "currency": "COP",
        "amount-in-cents": centavos,
        "reference": referencia,
        "signature:integrity": sha256(
            "%s%d%s%s" % (referencia, centavos, "COP", os.environ["WOMPI_INTEGRITY_SECRET"])
        ),
        "redirect-url": request.url_root.rstrip("/") + "/pago/respuesta",
        "customer-data:full-name": nombre,
    }
    if ciudad:
        params["shipping-address:city"] = ciudad
    return jsonify(url=WOMPI_CHECKOUT + "?" + urlencode(params), referencia=referencia)


def actualizar_estado(referencia, estado):
    conn = cursor = None
    try:
        conn = get_db()
        cursor = conn.cursor()
        cursor.execute("UPDATE compras SET estado = %s WHERE usuario LIKE %s",
                       (estado, "%| " + referencia))
        conn.commit()
    finally:
        if cursor is not None:
            cursor.close()
        if conn is not None and conn.is_connected():
            conn.close()


@app.route("/pago/webhook", methods=["POST"])
def pago_webhook():
    """Wompi avisa aquí el resultado final de cada transacción."""
    secreto = os.environ.get("WOMPI_EVENTS_SECRET")
    evento = request.get_json(silent=True) or {}
    if not secreto or evento.get("event") != "transaction.updated":
        return "", 200 if secreto else 503

    firma = evento.get("signature") or {}
    valores = ""
    for ruta in firma.get("properties", []):
        nodo = evento.get("data", {})
        for clave in ruta.split("."):
            nodo = nodo.get(clave, "") if isinstance(nodo, dict) else ""
        valores += str(nodo)
    esperado = sha256(valores + str(evento.get("timestamp", "")) + secreto)
    if not secrets.compare_digest(esperado, str(firma.get("checksum", ""))):
        abort(400)

    tx = evento["data"]["transaction"]
    estado = ESTADOS_PAGO.get(tx.get("status"), "PENDIENTE")
    try:
        actualizar_estado(tx["reference"], estado)
    except Exception:
        app.logger.exception("No se pudo actualizar la compra %s", tx.get("reference"))
        return "", 500
    return "", 200


@app.route("/pago/respuesta")
def pago_respuesta():
    """Página a la que Wompi devuelve al cliente después de PSE (?id=<transacción>)."""
    estado, referencia = "PENDING", ""
    tx_id = request.args.get("id", "")
    if tx_id.replace("-", "").isalnum():
        try:
            r = requests.get("%s/transactions/%s" % (wompi_api(), tx_id), timeout=10)
            tx = r.json().get("data", {})
            estado = tx.get("status", "PENDING")
            referencia = tx.get("reference", "")
        except Exception:
            app.logger.exception("No se pudo consultar la transacción %s", tx_id)
    return render_template("pago_respuesta.html", estado=estado, referencia=referencia)


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
