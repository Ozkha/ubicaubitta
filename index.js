document.addEventListener("DOMContentLoaded", () => {


const searchInput = document.getElementById("search-input");
const searchButton = document.getElementById("search-button");
const clearButton = document.getElementById("clear-button");

const allLocationItems = document.querySelectorAll(".location-item");
const allCategorySections = document.querySelectorAll(".category-section");


function aplicarFiltro(termino) {
    const terminoBusqueda = termino.toLowerCase().trim();

    allLocationItems.forEach((item) => {
        const nombreLugar = item.querySelector(".location-details p").textContent.toLowerCase();
        if (nombreLugar.includes(terminoBusqueda)) {
            item.style.display = "flex";
        } else {
            item.style.display = "none";
        }
    });

    allCategorySections.forEach((section) => {
        const itemsVisibles = section.querySelectorAll('.location-item[style*="display: flex"], .location-item:not([style*="display: none"])');
        if (itemsVisibles.length > 0) {
            section.style.display = "block";
        } else {
            section.style.display = "none";
        }
    });
}

searchButton.addEventListener("click", () => {
    aplicarFiltro(searchInput.value);
});

searchInput.addEventListener("keyup", (event) => {
    if (event.key === "Enter") {
        aplicarFiltro(searchInput.value);
    }
});

clearButton.addEventListener("click", () => {
    searchInput.value = "";
    aplicarFiltro("");  
});



    const mapSection = document.getElementById("container");
    const width = mapSection.offsetWidth;
    const height = mapSection.offsetHeight;

    const coloresPiso = {
        0: "#0077be",
        1: "#ff8c00",
        2: "#32cd32",
    };

    const stage = new Konva.Stage({
        container: "container",
        width: width,
        height: height,
        draggable: true,
    });

    const mapLayer = new Konva.Layer();
    const routeLayer = new Konva.Layer();
    stage.add(mapLayer, routeLayer);

    const modal = document.getElementById("route-modal");
    const modalCloseButton = modal.querySelector(".modal-close");
    const modalOverlay = document.querySelector(".modal-overlay");
    const locationItems = document.querySelectorAll(".location-item");

    const modalLugarNombre = document.getElementById("modal-lugar-nombre");
    const modalDistanciaTotal = document.getElementById("modal-distancia-total");
    const modalTiempoTotal = document.getElementById("modal-tiempo-total");
    const modalReferenciasLista = document.getElementById("modal-referencias-lista");
    const modalMapButton = document.getElementById("modal-map-button");
    
    let datosRutaActual = null;

    function dibujarRuta(rutaCompleta) {
        if (!rutaCompleta || rutaCompleta.length === 0) return;
        routeLayer.destroyChildren();

        const puntoInicio = rutaCompleta[0];
        const marcadorInicio = new Konva.Circle({
            x: puntoInicio.cord_x,
            y: puntoInicio.cord_y,
            radius: 10,
            fill: coloresPiso[puntoInicio.piso],
            stroke: "white",
            strokeWidth: 3,
            shadowColor: "blue",
            shadowBlur: 10,
            shadowOpacity: 0.8,
        });
        routeLayer.add(marcadorInicio);

        if (rutaCompleta.length >= 2) {
            for (let i = 0; i < rutaCompleta.length - 1; i++) {
                const puntoActual = rutaCompleta[i];
                const puntoSiguiente = rutaCompleta[i + 1];

                if (puntoActual.piso !== puntoSiguiente.piso) {
                    const transicion = new Konva.Ring({
                        x: puntoActual.cord_x,
                        y: puntoActual.cord_y,
                        innerRadius: 8,
                        outerRadius: 12,
                        fill: coloresPiso[puntoSiguiente.piso],
                        stroke: "black",
                        strokeWidth: 1,
                    });
                    routeLayer.add(transicion);
                    continue;
                }

                const lineaSegmento = new Konva.Line({
                    points: [
                        puntoActual.cord_x,
                        puntoActual.cord_y,
                        puntoSiguiente.cord_x,
                        puntoSiguiente.cord_y,
                    ],
                    stroke: coloresPiso[puntoActual.piso],
                    strokeWidth: 4,
                    lineCap: "round",
                    lineJoin: "round",
                    opacity: 0.9,
                });
                routeLayer.add(lineaSegmento);
            }
        }

        if (rutaCompleta.length > 1) {
            const puntoFinal = rutaCompleta[rutaCompleta.length - 1];
            const marcadorDestino = new Konva.Circle({
                x: puntoFinal.cord_x,
                y: puntoFinal.cord_y,
                radius: 10,
                fill: coloresPiso[puntoFinal.piso],
                stroke: "white",
                strokeWidth: 3,
                shadowColor: "red",
                shadowBlur: 10,
                shadowOpacity: 0.8,
            });
            routeLayer.add(marcadorDestino);
        }
    }

    function completarModal(data) {
        if (!data || !data.ruta || data.ruta.length === 0) return;

        const VELOCIDAD_METROS_POR_MINUTO = 50;
        const destino = data.ruta[data.ruta.length - 1];

        const modalFoto = document.getElementById('modal-foto');

        
        if (destino.ruta_fotografia) {
            modalFoto.innerHTML = `<img src="${destino.ruta_fotografia}" alt="Foto de ${destino.nombre}">`;
        } else {
            modalFoto.innerHTML = '<span>Sin foto</span>';
        }

        modalLugarNombre.textContent = destino.nombre;
        modalDistanciaTotal.textContent = `Distancia: ${Math.round(data.distancia_total)} m`;
        modalTiempoTotal.textContent = `Tiempo: ~${Math.ceil(data.distancia_total / VELOCIDAD_METROS_POR_MINUTO)} min`;
        
        function getFloorName(floorNumber) {
            if (floorNumber === 0) {
                return "Planta Baja";
            }
            return `Piso ${floorNumber}`;
        }
        
        modalReferenciasLista.innerHTML = "";

        let numeroReferencia = 1;

    for (let i = 1; i < data.ruta.length; i++) {
        const puntoActual = data.ruta[i];
        
        if (!puntoActual.nombre.toLowerCase().includes('camino') && i < data.ruta.length - 1) {
            
            const nombrePiso = getFloorName(puntoActual.piso);
            const li = document.createElement('li');
            
            li.innerHTML = `
                <div class="number">${numeroReferencia}</div>
                <div class="details">
                    <p>Pasar por: <strong>${puntoActual.nombre}</strong> <span class="floor-tag">(${nombrePiso})</span></p>
                </div>
            `;
            modalReferenciasLista.appendChild(li);

            numeroReferencia++;
        }
    }
    }

    locationItems.forEach((item) => {
        item.addEventListener("click", async (event) => {
            const destinoId = event.currentTarget.dataset.idLugar;
            if (!destinoId) return;

            modalLugarNombre.textContent = "cargando...";
            modal.style.display = "flex";

            try {
                const response = await fetch(`./ruta.php?destino=${destinoId}`);
                if (!response.ok) throw new Error(`Error: ${response.status}`);
                
                datosRutaActual = await response.json();
                completarModal(datosRutaActual);

            } catch (error) {
                console.error("nose pudo obtener la ruta:", error);
                alert("No se pudo cargar la info de la ruta");
                modal.style.display = "none";
            }
        });
    });

    modalMapButton.addEventListener('click', () => {
        if (datosRutaActual && datosRutaActual.ruta) {
            dibujarRuta(datosRutaActual.ruta);
            const destino = datosRutaActual.ruta[datosRutaActual.ruta.length - 1];
            fetch('guardar_historial.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id: destino.id })
            }).catch(error => console.error('Error al guardar el historial:', error));
            modal.style.display = "none";
        } else {
            console.error("No hay datos de ruta para dibujar");
        }
    });
    modalCloseButton.addEventListener("click", () => modal.style.display = "none");
    modalOverlay.addEventListener("click", (event) => {
        if (event.target === modalOverlay) {
            modal.style.display = "none";
        }
    });


    const imageObj = new Image();
    imageObj.src = "mapa.png"; 
    imageObj.onload = function () {
        const mapImage = new Konva.Image({
            x: 0,
            y: 0,
            image: imageObj,
        });
        mapLayer.add(mapImage);
    };
});