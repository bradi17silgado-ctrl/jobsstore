/* OBSCOOL — carrito de pedido + WhatsApp */
(function () {
    "use strict";

    var D = window.OBS || { whatsapp: "", productos: [] };
    var MAP = {};
    D.productos.forEach(function (p) { MAP[p.id] = p; });

    var KEY = "obscool_pedido_v1";
    var cart = {};             // { id: cantidad }
    var $ = function (s, r) { return (r || document).querySelector(s); };
    var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

    /* ---------- utilidades ---------- */
    function cop(n) { return "$" + Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, "."); }
    function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; }); }
    function imgUrl(p) { return p.imagen_url; }

    function load() {
        try {
            var raw = JSON.parse(localStorage.getItem(KEY) || "{}");
            Object.keys(raw).forEach(function (id) {
                var q = parseInt(raw[id], 10);
                if (MAP[id] && q > 0) cart[id] = Math.min(q, 99);
            });
        } catch (e) { cart = {}; }
    }
    function save() { try { localStorage.setItem(KEY, JSON.stringify(cart)); } catch (e) {} }

    function totals() {
        var n = 0, t = 0;
        Object.keys(cart).forEach(function (id) { n += cart[id]; t += cart[id] * MAP[id].precio; });
        return { n: n, t: t };
    }

    var toastTimer;
    function toast(msg) {
        var el = $("#toast"); el.textContent = msg; el.classList.add("show");
        clearTimeout(toastTimer); toastTimer = setTimeout(function () { el.classList.remove("show"); }, 2200);
    }

    /* ---------- WhatsApp ---------- */
    function openWA(text) {
        var url = "https://wa.me/" + D.whatsapp + "?text=" + encodeURIComponent(text);
        var w = window.open(url, "_blank", "noopener");
        if (!w) window.location.href = url;   // si el navegador bloquea la ventana
    }

    function consultar(id) {
        var p = MAP[id]; if (!p) return;
        openWA("¡Hola OBSCOOL! 👋\nMe interesa este producto:\n\n" +
               "• " + p.nombre + " (" + cop(p.precio) + " COP)\n\n" +
               "¿Está disponible? Quisiera más información. ¡Gracias!");
    }

    function enviarPedido() {
        var ids = Object.keys(cart);
        if (!ids.length) return;
        var nombre = $("#nombreCliente").value.trim();
        var ciudad = $("#ciudadCliente").value.trim();
        try { localStorage.setItem(KEY + "_cli", JSON.stringify({ n: nombre, c: ciudad })); } catch (e) {}

        var lineas = ids.map(function (id) {
            var p = MAP[id], q = cart[id];
            return "• " + q + " x " + p.nombre + " — " + cop(p.precio * q);
        });
        var msg = "¡Hola OBSCOOL! 👋 Quiero hacer este pedido:\n\n" + lineas.join("\n") +
                  "\n\n*Total: " + cop(totals().t) + " COP*" +
                  (nombre ? "\n\nNombre: " + nombre : "") +
                  (ciudad ? "\nCiudad/dirección: " + ciudad : "") +
                  "\n\n¿Me confirman disponibilidad, forma de pago y envío? ¡Gracias!";
        openWA(msg);
    }

    /* ---------- pago con PSE ---------- */
    function pagarPse() {
        if (!Object.keys(cart).length) return;
        var btn = $("#pagarPse"), txt = btn.innerHTML;
        var nombre = $("#nombreCliente").value.trim();
        var ciudad = $("#ciudadCliente").value.trim();
        try { localStorage.setItem(KEY + "_cli", JSON.stringify({ n: nombre, c: ciudad })); } catch (e) {}
        btn.disabled = true; btn.textContent = "Conectando con PSE…";

        fetch("/pago/pse", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ items: cart, nombre: nombre, ciudad: ciudad })
        }).then(function (r) {
            return r.json().then(function (d) { if (!r.ok) throw new Error(d.error || "Error"); return d; });
        }).then(function (d) {
            window.location.href = d.url;   // checkout de Wompi -> PSE
        }).catch(function (e) {
            btn.disabled = false; btn.innerHTML = txt;
            toast(e.message || "No pudimos iniciar el pago. Intenta de nuevo.");
        });
    }

    /* ---------- carrito ---------- */
    function add(id, delta) {
        if (!MAP[id]) return;
        var q = (cart[id] || 0) + delta;
        if (q <= 0) delete cart[id]; else cart[id] = Math.min(q, 99);
        save(); render();
    }
    function remove(id) { delete cart[id]; save(); render(); }

    function render() {
        var tt = totals();
        var badge = $("#contadorCarrito");
        badge.textContent = tt.n; badge.hidden = tt.n === 0;

        var list = $("#listaCarrito"), foot = $("#pieCarrito");
        if (!tt.n) {
            list.innerHTML = '<div class="carrito-vacio"><svg width="56" height="56" viewBox="0 0 24 24"><use href="#i-bag"/></svg>' +
                '<p><b>Tu pedido está vacío</b></p><p>Agrega productos desde el catálogo.</p>' +
                '<a class="btn btn-primary" href="/catalogo">Ver catálogo</a></div>';
            foot.hidden = true;
        } else {
            list.innerHTML = Object.keys(cart).map(function (id) {
                var p = MAP[id], q = cart[id];
                return '<div class="item">' +
                    '<img src="' + imgUrl(p) + '" alt="" width="64" height="64">' +
                    '<div><h4>' + esc(p.nombre) + '</h4><div class="p">' + cop(p.precio) + ' c/u</div>' +
                    '<div class="qty"><button type="button" data-dec="' + id + '" aria-label="Quitar uno">−</button>' +
                    '<span>' + q + '</span><button type="button" data-inc="' + id + '" aria-label="Agregar uno">+</button></div></div>' +
                    '<div class="item-side"><b>' + cop(p.precio * q) + '</b>' +
                    '<button type="button" class="item-del" data-del="' + id + '">Quitar</button></div></div>';
            }).join("");
            foot.hidden = false;
            $("#totalCarrito").textContent = cop(tt.t);
        }
        // marca tarjetas ya agregadas
        $$(".card").forEach(function (c) { c.classList.toggle("added", !!cart[c.dataset.id]); });
        $$("[data-add]").forEach(function (b) {
            b.textContent = cart[b.dataset.add] ? "✓ En el pedido (" + cart[b.dataset.add] + ")" : "Agregar al pedido";
        });
    }

    function openDrawer() {
        $("#drawer").classList.add("open"); $("#drawer").setAttribute("aria-hidden", "false");
        $("#overlay").hidden = false; document.body.classList.add("no-scroll", "drawer-open");
        $("#cerrarCarrito").focus();
    }
    function closeDrawer() {
        $("#drawer").classList.remove("open"); $("#drawer").setAttribute("aria-hidden", "true");
        $("#overlay").hidden = true; document.body.classList.remove("no-scroll", "drawer-open");
    }

    /* ---------- catálogo: filtros ---------- */
    function initCatalogo() {
        var grid = $("#productos"); if (!grid) return;
        var cards = $$(".card", grid);
        var state = { cat: "todos", q: "", orden: "orig" };
        var original = cards.slice();

        var params = new URLSearchParams(location.search);
        var c = params.get("c");
        if (c && cards.some(function (el) { return el.dataset.categoria === c; })) state.cat = c;

        function apply() {
            var q = state.q.toLowerCase().trim(), shown = 0;
            var ordered = original.slice();
            if (state.orden !== "orig") {
                ordered.sort(function (a, b) { return (a.dataset.precio - b.dataset.precio) * (state.orden === "asc" ? 1 : -1); });
            }
            ordered.forEach(function (el) {
                var ok = (state.cat === "todos" || el.dataset.categoria === state.cat) &&
                         (!q || el.dataset.nombre.indexOf(q) !== -1);
                el.hidden = !ok; if (ok) shown++;
                grid.appendChild(el);
            });
            $("#sinResultados").hidden = shown !== 0;
            $$(".tab").forEach(function (t) {
                var on = t.dataset.cat === state.cat;
                t.classList.toggle("is-on", on); t.setAttribute("aria-selected", on);
            });
        }
        $$(".tab").forEach(function (t) { t.addEventListener("click", function () { state.cat = t.dataset.cat; apply(); }); });
        $("#buscador").addEventListener("input", function (e) { state.q = e.target.value; apply(); });
        $("#orden").addEventListener("change", function (e) { state.orden = e.target.value; apply(); });
        apply();
    }

    /* ---------- eventos globales ---------- */
    document.addEventListener("click", function (e) {
        var t = e.target.closest("[data-add],[data-consultar],[data-inc],[data-dec],[data-del]");
        if (!t) return;
        if (t.dataset.add) {
            add(t.dataset.add, 1);
            toast("Agregado: " + MAP[t.dataset.add].nombre);
            var b = $("#contadorCarrito"); b.classList.remove("bump"); void b.offsetWidth; b.classList.add("bump");
        }
        else if (t.dataset.consultar) consultar(t.dataset.consultar);
        else if (t.dataset.inc) add(t.dataset.inc, 1);
        else if (t.dataset.dec) add(t.dataset.dec, -1);
        else if (t.dataset.del) remove(t.dataset.del);
    });

    $("#abrirCarrito").addEventListener("click", openDrawer);
    $("#cerrarCarrito").addEventListener("click", closeDrawer);
    $("#overlay").addEventListener("click", closeDrawer);
    document.addEventListener("keydown", function (e) { if (e.key === "Escape") closeDrawer(); });
    $("#enviarPedido").addEventListener("click", enviarPedido);
    var pse = $("#pagarPse"); if (pse) pse.addEventListener("click", pagarPse);
    $("#vaciarCarrito").addEventListener("click", function () {
        if (confirm("¿Vaciar todo el pedido?")) { cart = {}; save(); render(); }
    });

    var burger = $("#burger");
    burger.addEventListener("click", function () {
        var open = $("#nav").classList.toggle("open");
        burger.setAttribute("aria-expanded", open);
    });

    var y = $("#anio"); if (y) y.textContent = new Date().getFullYear();

    load();
    try {
        var cli = JSON.parse(localStorage.getItem(KEY + "_cli") || "{}");
        $("#nombreCliente").value = cli.n || ""; $("#ciudadCliente").value = cli.c || "";
    } catch (e) {}
    render();
    initCatalogo();
})();
