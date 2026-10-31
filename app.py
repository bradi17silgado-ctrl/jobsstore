from flask import (Flask, render_template, request, redirect, abort, jsonify,
                   session, flash, url_for, Response, g)
from urllib.parse import urlencode
import hashlib
import re
import secrets
import time
import unicodedata
from functools import wraps
import mysql.connector
import os
import requests

app = Flask(__name__)
app.secret_key = os.environ.get("SECRET_KEY", "sager-secret-2025")
app.config.update(
    MAX_CONTENT_LENGTH=6 * 1024 * 1024,          # tope de cualquier subida
    SESSION_COOKIE_HTTPONLY=True,
    SESSION_COOKIE_SAMESITE="Lax",
    # En producción (https) pon SESSION_COOKIE_SECURE=1
    SESSION_COOKIE_SECURE=os.environ.get("SESSION_COOKIE_SECURE") == "1",
)


# ==========================================
# CONFIGURACIÓN DEL NEGOCIO
# ==========================================

NEGOCIO = {
    "nombre": "JOBS Store",
    "lema": "Creatividad · Innovación · Estilo",
    # Número de WhatsApp con indicativo de país, sin + ni espacios
    "whatsapp": os.environ.get("WHATSAPP_NUMBER", "573227879835"),
}

# Productos iniciales. Solo se copian a la base de datos UNA vez (cuando se crea
# la tabla "productos"); desde ahí se gestionan en /admin.
# También sirven de respaldo si la base de datos no responde.
# "destacado": True -> aparece en la página de inicio.
PRODUCTOS_INICIALES = [
    # ---------- GORRAS ----------
    {"id": "g1", "nombre": "Gorra Plana Azul Royal", "categoria": "gorra",
     "descripcion": "Corte plano, ajuste firme y bordado frontal.",
     "precio": 55000, "imagen": "productos/gorra-azul-royal.webp", "destacado": True},
    {"id": "g2", "nombre": "Gorra Clásica Negra", "categoria": "gorra",
     "descripcion": "Diseño curvo y ligero para uso diario.",
     "precio": 45000, "imagen": "productos/gorra-negra-clasica.webp", "destacado": False},
    {"id": "g3", "nombre": "Gorra Plana Azul Marino", "categoria": "gorra",
     "descripcion": "Estilo urbano que combina con todo.",
     "precio": 40000, "imagen": "productos/gorra-azul-marino.webp", "destacado": False},
    {"id": "g4", "nombre": "Gorra Plana Verde y Dorado", "categoria": "gorra",
     "descripcion": "Contraste de colores para destacar.",
     "precio": 55000, "imagen": "productos/gorra-verde-dorado.webp", "destacado": False},
    {"id": "g5", "nombre": "Gorra Plana Negra y Roja", "categoria": "gorra",
     "descripcion": "Look deportivo con detalle bordado.",
     "precio": 55000, "imagen": "productos/gorra-negra-roja.webp", "destacado": False},
    {"id": "g6", "nombre": "Gorra Crema y Verde", "categoria": "gorra",
     "descripcion": "Tono claro con visera contrastante.",
     "precio": 55000, "imagen": "productos/gorra-crema-verde.webp", "destacado": False},
    # ---------- RELOJES ----------
    {"id": "r1", "nombre": "Reloj Elegante Dorado", "categoria": "reloj",
     "descripcion": "Diseño clásico para ocasiones especiales.",
     "precio": 120000, "imagen": "productos/reloj-dorado.webp", "destacado": True},
    {"id": "r2", "nombre": "Reloj Minimalista Azul Hielo", "categoria": "reloj",
     "descripcion": "Esfera limpia, moderno y versátil.",
     "precio": 95000, "imagen": "productos/reloj-azul-hielo.webp", "destacado": True},
    {"id": "r3", "nombre": "Reloj Deportivo Acero", "categoria": "reloj",
     "descripcion": "Robusto y con estilo para todos los días.",
     "precio": 110000, "imagen": "productos/reloj-deportivo-acero.webp", "destacado": False},
    {"id": "r4", "nombre": "Reloj Esqueleto Oro Rosa", "categoria": "reloj",
     "descripcion": "Esfera esqueleto con correa de silicona.",
     "precio": 110000, "imagen": "productos/reloj-esqueleto-oro-rosa.webp", "destacado": True},
    {"id": "r5", "nombre": "Reloj Esqueleto Naranja", "categoria": "reloj",
     "descripcion": "Caja negra y correa naranja llamativa.",
     "precio": 110000, "imagen": "productos/reloj-esqueleto-naranja.webp", "destacado": False},
    {"id": "r6", "nombre": "Reloj Cronógrafo Titanio", "categoria": "reloj",
     "descripcion": "Acabado gris titanio de aire premium.",
     "precio": 110000, "imagen": "productos/reloj-cronografo-titanio.webp", "destacado": False},
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
    productos = obtener_productos()
    return {
        "negocio": NEGOCIO,
        "todos_productos": productos,
        "categorias": categorias_de(productos),
        "pse_activo": pse_configurado(),
    }


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
# PRODUCTOS (base de datos)
# Tabla "productos" en MySQL. Las fotos subidas desde el panel se guardan en la
# propia base (no en disco) para que no se pierdan cuando Railway reinicia o
# vuelve a desplegar la app.
# ==========================================

CATEGORIAS_CONOCIDAS = {"gorra": "Gorras", "reloj": "Relojes"}
TIPOS_IMAGEN = {"image/jpeg": "jpg", "image/png": "png", "image/webp": "webp", "image/gif": "gif"}
MAX_IMAGEN = 3 * 1024 * 1024
PRECIO_MAX = 99_999_999

_esquema_listo = False


def slug(texto):
    """'Gorra Plana' -> 'gorra-plana' (sin tildes ni símbolos)."""
    t = unicodedata.normalize("NFKD", str(texto or "")).encode("ascii", "ignore").decode()
    return re.sub(r"[^a-z0-9]+", "-", t.lower()).strip("-")[:40]


def etiqueta_categoria(cat):
    return CATEGORIAS_CONOCIDAS.get(cat) or cat.replace("-", " ").capitalize()


def detectar_imagen(datos):
    """Devuelve el tipo MIME real según los primeros bytes (no confía en el nombre)."""
    if datos[:3] == b"\xff\xd8\xff":
        return "image/jpeg"
    if datos[:8] == b"\x89PNG\r\n\x1a\n":
        return "image/png"
    if datos[:4] == b"RIFF" and datos[8:12] == b"WEBP":
        return "image/webp"
    if datos[:6] in (b"GIF87a", b"GIF89a"):
        return "image/gif"
    return None


def asegurar_esquema():
    """Crea la tabla de productos si no existe y, solo la primera vez, la llena
    con PRODUCTOS_INICIALES. Así borrar un producto no lo hace reaparecer."""
    global _esquema_listo
    if _esquema_listo:
        return
    conn = get_db()
    try:
        cur = conn.cursor()
        cur.execute("SELECT COUNT(*) FROM information_schema.tables "
                    "WHERE table_schema = DATABASE() AND table_name = 'productos'")
        existia = cur.fetchone()[0] > 0
        cur.execute("""
            CREATE TABLE IF NOT EXISTS productos (
                id           VARCHAR(20)  NOT NULL PRIMARY KEY,
                nombre       VARCHAR(120) NOT NULL,
                categoria    VARCHAR(40)  NOT NULL,
                descripcion  VARCHAR(500) NOT NULL DEFAULT '',
                precio       INT          NOT NULL,
                imagen       VARCHAR(200) NULL,
                imagen_data  MEDIUMBLOB   NULL,
                imagen_tipo  VARCHAR(40)  NULL,
                destacado    TINYINT(1)   NOT NULL DEFAULT 0,
                activo       TINYINT(1)   NOT NULL DEFAULT 1,
                orden        INT          NOT NULL DEFAULT 0,
                actualizado  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                                          ON UPDATE CURRENT_TIMESTAMP
            ) DEFAULT CHARSET = utf8mb4
        """)
        if not existia:
            for i, p in enumerate(PRODUCTOS_INICIALES):
                cur.execute(
                    "INSERT IGNORE INTO productos "
                    "(id, nombre, categoria, descripcion, precio, imagen, destacado, activo, orden) "
                    "VALUES (%s, %s, %s, %s, %s, %s, %s, 1, %s)",
                    (p["id"], p["nombre"], p["categoria"], p["descripcion"], p["precio"],
                     p["imagen"], 1 if p["destacado"] else 0, (i + 1) * 10))
        conn.commit()
        _esquema_listo = True
    finally:
        conn.close()


def _producto_dict(fila):
    """Fila de la BD -> dict listo para plantillas y JS (sin los bytes de la foto)."""
    if fila["tiene_foto"]:
        imagen_url = url_for("foto_producto", pid=fila["id"], v=fila["version"])
    elif fila["imagen"]:
        imagen_url = url_for("static", filename="img/" + fila["imagen"])
    else:
        imagen_url = url_for("static", filename="img/logo-emblema.png")
    return {
        "id": fila["id"],
        "nombre": fila["nombre"],
        "categoria": fila["categoria"],
        "categoria_label": etiqueta_categoria(fila["categoria"]),
        "descripcion": fila["descripcion"],
        "precio": int(fila["precio"]),
        "imagen_url": imagen_url,
        "tiene_foto": bool(fila["tiene_foto"]),
        "destacado": bool(fila["destacado"]),
        "activo": bool(fila["activo"]),
    }


def _productos_respaldo():
    out = []
    for p in PRODUCTOS_INICIALES:
        q = dict(p)
        q.update(categoria_label=etiqueta_categoria(p["categoria"]), activo=True, tiene_foto=False,
                 imagen_url=url_for("static", filename="img/" + p["imagen"]))
        out.append(q)
    return out


SQL_PRODUCTO = ("SELECT id, nombre, categoria, descripcion, precio, imagen, destacado, activo, "
                "(imagen_data IS NOT NULL) AS tiene_foto, UNIX_TIMESTAMP(actualizado) AS version "
                "FROM productos ")


def obtener_productos(incluir_ocultos=False):
    """Productos de la tienda. En las páginas públicas, si la BD falla se usa la
    lista inicial para que la tienda siga viva; en el panel el error se propaga."""
    if not incluir_ocultos and hasattr(g, "_productos"):
        return g._productos
    conn = None
    try:
        asegurar_esquema()
        conn = get_db()
        cur = conn.cursor(dictionary=True)
        cur.execute(SQL_PRODUCTO + ("" if incluir_ocultos else "WHERE activo = 1 ")
                    + "ORDER BY orden ASC, nombre ASC")
        lista = [_producto_dict(f) for f in cur.fetchall()]
    except Exception:
        if incluir_ocultos:
            raise
        app.logger.exception("No se pudieron leer los productos; usando la lista inicial")
        lista = _productos_respaldo()
    finally:
        if conn is not None and conn.is_connected():
            conn.close()
    if not incluir_ocultos:
        g._productos = lista
    return lista


def obtener_producto(pid):
    conn = get_db()
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute(SQL_PRODUCTO + "WHERE id = %s", (pid,))
        fila = cur.fetchone()
        return _producto_dict(fila) if fila else None
    finally:
        conn.close()


def categorias_de(productos):
    """[(slug, etiqueta, cantidad)] solo de categorías con productos visibles."""
    cuenta = {}
    for p in productos:
        cuenta[p["categoria"]] = cuenta.get(p["categoria"], 0) + 1
    orden = sorted(cuenta, key=lambda c: (c not in CATEGORIAS_CONOCIDAS, etiqueta_categoria(c)))
    return [(c, etiqueta_categoria(c), cuenta[c]) for c in orden]


@app.route("/producto-img/<pid>")
def foto_producto(pid):
    conn = get_db()
    try:
        cur = conn.cursor()
        cur.execute("SELECT imagen_data, imagen_tipo FROM productos WHERE id = %s AND imagen_data IS NOT NULL", (pid,))
        fila = cur.fetchone()
    finally:
        conn.close()
    if not fila:
        abort(404)
    resp = Response(bytes(fila[0]), mimetype=fila[1] or "image/jpeg")
    # La URL lleva ?v=<versión>, así que se puede cachear "para siempre".
    resp.headers["Cache-Control"] = "public, max-age=31536000, immutable"
    resp.headers["X-Content-Type-Options"] = "nosniff"
    return resp


# ==========================================
# PÁGINAS PÚBLICAS
# ==========================================

@app.route("/")
def home():
    return redirect("/inicio")


@app.route("/inicio")
def inicio():
    destacados = [p for p in obtener_productos() if p["destacado"]]
    return render_template("index.html", destacados=destacados)


@app.route("/catalogo")
def catalogo():
    return render_template("catalogo.html", productos=obtener_productos())


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

    catalogo = {p["id"]: p for p in obtener_productos()}
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
    referencia = "JOBS-%d-%s" % (int(time.time()), secrets.token_hex(3).upper())

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
# ADMINISTRACIÓN
# Variable de entorno ADMIN_PASSWORD = contraseña del panel (/admin).
# (Si no existe se usa ADMIN_KEY, la clave que ya tenías para /compras.)
# Sin ninguna de las dos, el panel queda desactivado.
# ==========================================

def clave_admin():
    return os.environ.get("ADMIN_PASSWORD") or os.environ.get("ADMIN_KEY") or ""


def iguales(a, b):
    return secrets.compare_digest(str(a).encode("utf-8"), str(b).encode("utf-8"))


def es_admin():
    return session.get("admin") is True


def admin_requerido(vista):
    @wraps(vista)
    def envoltura(*args, **kwargs):
        if not clave_admin():
            abort(404)
        if not es_admin():
            return redirect(url_for("admin_login", next=request.path))
        return vista(*args, **kwargs)
    return envoltura


def token_csrf():
    if "csrf" not in session:
        session["csrf"] = secrets.token_urlsafe(24)
    return session["csrf"]


app.jinja_env.globals["csrf_token"] = token_csrf


@app.before_request
def proteger_admin():
    """Todo POST bajo /admin debe traer el token CSRF de la sesión."""
    if request.method == "POST" and request.path.startswith("/admin"):
        enviado = request.form.get("csrf", "")
        if not enviado or not iguales(enviado, session.get("csrf", "")):
            abort(400)


@app.after_request
def no_indexar_admin(resp):
    if request.path.startswith("/admin") or request.path == "/compras":
        resp.headers["Cache-Control"] = "no-store"
        resp.headers["X-Robots-Tag"] = "noindex, nofollow"
    return resp


_intentos = {}   # ip -> [marcas de tiempo de intentos fallidos]


def _ip():
    return request.remote_addr or "?"


@app.route("/admin/login", methods=["GET", "POST"])
def admin_login():
    if not clave_admin():
        abort(404)
    destino = request.args.get("next", "")
    if not destino.startswith("/admin") or destino.startswith("//"):
        destino = url_for("admin_panel")
    if es_admin():
        return redirect(destino)

    error = None
    if request.method == "POST":
        ahora = time.time()
        recientes = [t for t in _intentos.get(_ip(), []) if ahora - t < 300]
        if len(recientes) >= 5:
            error = "Demasiados intentos. Espera unos minutos e inténtalo de nuevo."
        elif iguales(request.form.get("password", ""), clave_admin()):
            _intentos.pop(_ip(), None)
            session.clear()
            session["admin"] = True
            session.permanent = False
            return redirect(destino)
        else:
            recientes.append(ahora)
            error = "Contraseña incorrecta."
        _intentos[_ip()] = recientes
    return render_template("admin_login.html", error=error)


@app.route("/admin/salir", methods=["POST"])
def admin_salir():
    session.clear()
    return redirect(url_for("admin_login"))


@app.route("/admin")
@admin_requerido
def admin_panel():
    try:
        productos = obtener_productos(incluir_ocultos=True)
        error_bd = None
    except Exception:
        app.logger.exception("No se pudo leer la tabla de productos")
        productos, error_bd = [], "No se pudo conectar con la base de datos."
    return render_template("admin_productos.html", productos=productos, error_bd=error_bd)


def leer_formulario():
    """Valida el formulario de producto. Devuelve (datos, imagen, errores)."""
    f = request.form
    errores = []

    nombre = " ".join(f.get("nombre", "").split())
    if not nombre:
        errores.append("Escribe el nombre del producto.")
    elif len(nombre) > 120:
        errores.append("El nombre es demasiado largo (máx. 120 caracteres).")

    categoria = slug(f.get("categoria", ""))
    if not categoria:
        errores.append("Indica la categoría (por ejemplo: gorra, reloj, camiseta).")

    descripcion = f.get("descripcion", "").strip()
    if len(descripcion) > 500:
        errores.append("La descripción es demasiado larga (máx. 500 caracteres).")

    digitos = re.sub(r"\D", "", f.get("precio", ""))
    precio = int(digitos) if digitos else 0
    if precio <= 0:
        errores.append("Escribe un precio mayor que cero (en pesos, sin decimales).")
    elif precio > PRECIO_MAX:
        errores.append("El precio es demasiado alto.")

    imagen = None
    archivo = request.files.get("foto")
    if archivo and archivo.filename:
        datos = archivo.read(MAX_IMAGEN + 1)
        if len(datos) > MAX_IMAGEN:
            errores.append("La foto pesa más de 3 MB. Usa una más liviana.")
        else:
            tipo = detectar_imagen(datos)
            if tipo is None:
                errores.append("La foto debe ser JPG, PNG, WEBP o GIF.")
            else:
                imagen = (datos, tipo)

    datos_producto = {
        "nombre": nombre, "categoria": categoria, "descripcion": descripcion,
        "precio": precio,
        "destacado": 1 if f.get("destacado") else 0,
        "activo": 1 if f.get("activo") else 0,
    }
    return datos_producto, imagen, errores


@app.route("/admin/productos/nuevo", methods=["GET", "POST"])
@admin_requerido
def admin_producto_nuevo():
    asegurar_esquema()
    categorias = categorias_admin()
    if request.method == "POST":
        datos, imagen, errores = leer_formulario()
        if not errores:
            pid = "p" + secrets.token_hex(4)
            conn = get_db()
            try:
                cur = conn.cursor()
                cur.execute("SELECT COALESCE(MIN(orden), 0) FROM productos")
                orden = int(cur.fetchone()[0]) - 10      # los nuevos salen primero
                cur.execute(
                    "INSERT INTO productos (id, nombre, categoria, descripcion, precio, "
                    "imagen_data, imagen_tipo, destacado, activo, orden) "
                    "VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)",
                    (pid, datos["nombre"], datos["categoria"], datos["descripcion"], datos["precio"],
                     imagen[0] if imagen else None, imagen[1] if imagen else None,
                     datos["destacado"], datos["activo"], orden))
                conn.commit()
            finally:
                conn.close()
            flash("Producto publicado: %s" % datos["nombre"], "ok")
            return redirect(url_for("admin_panel"))
        for e in errores:
            flash(e, "error")
        producto = dict(datos, id=None, tiene_foto=False, imagen_url=None)
        return render_template("admin_form.html", producto=producto, categorias=categorias), 400
    producto = {"id": None, "nombre": "", "categoria": "", "descripcion": "", "precio": "",
                "destacado": False, "activo": True, "tiene_foto": False, "imagen_url": None}
    return render_template("admin_form.html", producto=producto, categorias=categorias)


@app.route("/admin/productos/<pid>/editar", methods=["GET", "POST"])
@admin_requerido
def admin_producto_editar(pid):
    actual = obtener_producto(pid)
    if not actual:
        abort(404)
    categorias = categorias_admin()
    if request.method == "POST":
        datos, imagen, errores = leer_formulario()
        if not errores:
            quitar = bool(request.form.get("quitar_foto")) and not imagen
            conn = get_db()
            try:
                cur = conn.cursor()
                sql = ("UPDATE productos SET nombre=%s, categoria=%s, descripcion=%s, precio=%s, "
                       "destacado=%s, activo=%s")
                params = [datos["nombre"], datos["categoria"], datos["descripcion"], datos["precio"],
                          datos["destacado"], datos["activo"]]
                if imagen:
                    sql += ", imagen_data=%s, imagen_tipo=%s"
                    params += [imagen[0], imagen[1]]
                elif quitar:
                    sql += ", imagen_data=NULL, imagen_tipo=NULL"
                cur.execute(sql + " WHERE id=%s", params + [pid])
                conn.commit()
            finally:
                conn.close()
            flash("Cambios guardados: %s" % datos["nombre"], "ok")
            return redirect(url_for("admin_panel"))
        for e in errores:
            flash(e, "error")
        producto = dict(actual, **datos)
        producto["destacado"], producto["activo"] = bool(datos["destacado"]), bool(datos["activo"])
        return render_template("admin_form.html", producto=producto, categorias=categorias), 400
    return render_template("admin_form.html", producto=actual, categorias=categorias)


@app.route("/admin/productos/<pid>/visibilidad", methods=["POST"])
@admin_requerido
def admin_producto_visibilidad(pid):
    conn = get_db()
    try:
        cur = conn.cursor()
        cur.execute("UPDATE productos SET activo = 1 - activo WHERE id = %s", (pid,))
        conn.commit()
        if cur.rowcount == 0:
            abort(404)
    finally:
        conn.close()
    flash("Visibilidad actualizada.", "ok")
    return redirect(url_for("admin_panel"))


@app.route("/admin/productos/<pid>/eliminar", methods=["POST"])
@admin_requerido
def admin_producto_eliminar(pid):
    conn = get_db()
    try:
        cur = conn.cursor()
        cur.execute("DELETE FROM productos WHERE id = %s", (pid,))
        conn.commit()
        if cur.rowcount == 0:
            abort(404)
    finally:
        conn.close()
    flash("Producto eliminado.", "ok")
    return redirect(url_for("admin_panel"))


def categorias_admin():
    """Categorías ya usadas (incluye productos ocultos) para sugerir en el formulario."""
    conn = get_db()
    try:
        cur = conn.cursor()
        cur.execute("SELECT DISTINCT categoria FROM productos ORDER BY categoria")
        usadas = {r[0] for r in cur.fetchall()}
    finally:
        conn.close()
    return sorted(usadas | set(CATEGORIAS_CONOCIDAS))


# ==========================================
# COMPRAS (ADMINISTRACIÓN)
# Solo con sesión de administrador, o con /compras?key=TU_CLAVE
# ==========================================

@app.route("/compras")
def compras():
    clave = clave_admin()
    if not clave:
        abort(404)
    if not es_admin() and not iguales(request.args.get("key", ""), clave):
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
