<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Informes de control por CUI - Prueba de API</title>
    <style>
        body { font-family: system-ui, Arial, sans-serif; margin: 0; background: #f4f5f7; color: #1f2933; }
        main { max-width: 1100px; margin: 0 auto; padding: 24px 16px 48px; }
        h1 { font-size: 1.5rem; margin: 0 0 4px; }
        .sub { color: #52606d; margin: 0 0 20px; font-size: .95rem; }
        form { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; margin-bottom: 12px; }
        label { display: block; font-size: .85rem; margin-bottom: 4px; font-weight: 600; }
        input { padding: 9px 10px; font-size: 1rem; border: 1px solid #9aa5b1; border-radius: 6px; width: 180px; }
        button { padding: 10px 18px; font-size: 1rem; border: 0; border-radius: 6px; background: #b3261e; color: #fff; cursor: pointer; }
        button:disabled { opacity: .6; cursor: wait; }
        #estado { min-height: 1.4em; margin: 8px 0 16px; }
        #estado.error { color: #b3261e; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; background: #fff; font-size: .9rem; }
        th, td { border: 1px solid #d9dee3; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #e4e7eb; }
        details { margin-top: 16px; }
        pre { background: #111827; color: #e5e7eb; padding: 12px; border-radius: 6px; overflow: auto; max-height: 420px; font-size: .8rem; }
        .tabla-scroll { overflow-x: auto; }
    </style>
</head>
<body>
<main>
    <h1>Informes de control por CUI</h1>
    <p class="sub">Fuente: Buscador de Informes de Servicios de Control (Contraloría General de la República). Pantalla de prueba de la API.</p>

    <form id="form">
        <div>
            <label for="cui">CUI de la obra</label>
            <input id="cui" inputmode="numeric" maxlength="7" placeholder="Ej.: 2650667" autocomplete="off" required>
        </div>
        <button id="boton" type="submit">Consultar</button>
    </form>

    <p id="estado" role="status"></p>

    <section id="resultado" hidden>
        <div class="tabla-scroll" id="contenedor"></div>
        <details>
            <summary>Ver JSON de la respuesta</summary>
            <pre id="json"></pre>
        </details>
    </section>
</main>

<script>
    const base = @json(url('/api/contraloria/obras'));

    const form = document.getElementById('form');
    const boton = document.getElementById('boton');
    const estado = document.getElementById('estado');
    const resultado = document.getElementById('resultado');
    const contenedor = document.getElementById('contenedor');
    const jsonPre = document.getElementById('json');

    const columnas = [
        ['numero_informe', 'N° de informe'],
        ['descripcion', 'Título'],
        ['entidad', 'Entidad'],
        ['servicio_control', 'Servicio de control'],
        ['modalidad_servicio', 'Modalidad'],
        ['fecha_publicacion', 'Publicación'],
        ['url_resumen', 'Resumen'],
        ['url_informe', 'Informe'],
        ['relacion_cui', 'Relación con el CUI'],
    ];

    function celda(informe, campo) {
        const td = document.createElement('td');
        const valor = informe[campo];

        if (campo === 'url_resumen' || campo === 'url_informe') {
            // Solo se crean enlaces http/https, y todo el texto se inserta con textContent (sin HTML).
            if (typeof valor === 'string' && /^https?:\/\//i.test(valor)) {
                const a = document.createElement('a');
                a.href = valor;
                a.target = '_blank';
                a.rel = 'noopener noreferrer';
                a.textContent = 'Abrir PDF';
                td.appendChild(a);
            } else {
                td.textContent = '-';
            }
        } else {
            td.textContent = valor ?? '-';
        }

        return td;
    }

    function construirTabla(informes) {
        const tabla = document.createElement('table');
        const cabecera = tabla.createTHead().insertRow();

        for (const [, titulo] of columnas) {
            const th = document.createElement('th');
            th.textContent = titulo;
            cabecera.appendChild(th);
        }

        const cuerpo = tabla.createTBody();
        for (const informe of informes) {
            const fila = cuerpo.insertRow();
            for (const [campo] of columnas) {
                fila.appendChild(celda(informe, campo));
            }
        }

        return tabla;
    }

    form.addEventListener('submit', async (evento) => {
        evento.preventDefault();

        const cui = document.getElementById('cui').value.trim();

        estado.className = '';
        estado.textContent = 'Consultando...';
        resultado.hidden = true;
        boton.disabled = true;

        try {
            const respuesta = await fetch(`${base}/${encodeURIComponent(cui)}/informes`, {
                headers: { 'Accept': 'application/json' },
            });
            const datos = await respuesta.json();

            jsonPre.textContent = JSON.stringify(datos, null, 2);
            resultado.hidden = false;

            if (!datos.success) {
                estado.className = 'error';
                estado.textContent = `Error (${datos.error?.codigo ?? 'desconocido'}): ${datos.error?.mensaje ?? ''}`;
                contenedor.replaceChildren();
                return;
            }

            estado.textContent = `${datos.total} informe(s) con relación confirmada. ${datos.aviso}`;
            contenedor.replaceChildren(datos.total > 0 ? construirTabla(datos.informes) : '');
        } catch (error) {
            estado.className = 'error';
            estado.textContent = 'No se pudo comunicar con la API: ' + error.message;
        } finally {
            boton.disabled = false;
        }
    });
</script>
</body>
</html>
