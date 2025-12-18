@php
    // Imágenes del dashboard
    $slides = [
        [ 'src' => asset('presentaciones/Procedimiento para Solicitar Equipos, Accesorios y Repuestos/PSE_1.png') ],
        [ 'src' => asset('presentaciones/Procedimiento para Solicitar Equipos, Accesorios y Repuestos/PSE_2.png') ],
        [ 'src' => asset('presentaciones/Procedimiento para Solicitar Equipos, Accesorios y Repuestos/PSE_3.png') ],
        [ 'src' => asset('presentaciones/Procedimiento para Solicitar Equipos, Accesorios y Repuestos/PSE_4.png') ],
        [ 'src' => asset('presentaciones/Procedimiento para Solicitar Equipos, Accesorios y Repuestos/PSE_5.png') ],
        [ 'src' => asset('presentaciones/Procedimiento para Solicitar Equipos, Accesorios y Repuestos/PSE_6.png') ],
        [ 'src' => asset('presentaciones/Procedimiento para Solicitar Equipos, Accesorios y Repuestos/PSE_7.png') ],

        [ 'src' => asset('presentaciones/Cuidado de Baterias y Cargadores/CBC_1.jpg') ],
        [ 'src' => asset('presentaciones/Cuidado de Baterias y Cargadores/CBC_2.jpg') ],
        [ 'src' => asset('presentaciones/Cuidado de Baterias y Cargadores/CBC_3.jpg') ],
        [ 'src' => asset('presentaciones/Cuidado de Baterias y Cargadores/CBC_4.jpg') ],
        [ 'src' => asset('presentaciones/Cuidado de Baterias y Cargadores/CBC_5.jpg') ],
        [ 'src' => asset('presentaciones/Cuidado de Baterias y Cargadores/CBC_6.jpg') ],

        [ 'src' => asset('presentaciones/Fallas Electricas o Sobrecalentamiento/FES_1.jpg') ],
        [ 'src' => asset('presentaciones/Fallas Electricas o Sobrecalentamiento/FES_2.jpg') ],
        [ 'src' => asset('presentaciones/Fallas Electricas o Sobrecalentamiento/FES_3.jpg') ],
        [ 'src' => asset('presentaciones/Fallas Electricas o Sobrecalentamiento/FES_4.jpg') ],
        [ 'src' => asset('presentaciones/Fallas Electricas o Sobrecalentamiento/FES_5.jpg') ],
        [ 'src' => asset('presentaciones/Fallas Electricas o Sobrecalentamiento/FES_6.jpg') ],
    ];
@endphp

<x-app-layout title="Dashboard">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Panel
        </h2>
    </x-slot>

    <style>
        .slider-shell{
            max-width: 1000px;
            margin: 2rem auto;
            position: relative;
            width: 100%;
            aspect-ratio: 16 / 9;
            overflow: hidden;
            background:#ffffff;
        }

        .slider-track{
            position:relative;
            width:100%;
            height:100%;
        }

        .slide{
            position:absolute;
            inset:0;
            opacity:0;
            transition:opacity .6s ease-in-out;
            display:flex;
            align-items:center;
            justify-content:center;
        }
        .slide.is-active{
            opacity:1;
        }

        .slide img{
            width:100%;
            height:100%;
            object-fit:contain;
            background:#ffffff;
        }

        /* Flechas mínimas para navegación manual */
        .slider-nav{
            position:absolute;
            inset:0;
            display:flex;
            justify-content:space-between;
            align-items:center;
            pointer-events:none;
        }
        /* Flechas “desnudas” (solo icono) */
        .slider-btn{
            pointer-events:auto;
            background:transparent;     /* sin fondo */
            border:none;                /* sin borde */
            font-size:2.2rem;           /* tamaño de la flecha */
            color:rgba(0,0,0,.25);      /* flecha gris muy clara / semi-transparente */
            cursor:pointer;
            margin:0 .75rem;
            display:flex;
            align-items:center;
            justify-content:center;
            transition:color .15s, transform .15s;
        }

        .slider-btn:hover{
            color:#111827;              /* flecha negra/gris oscuro al pasar el mouse */
            transform:translateY(-1px); /* pequeño efecto opcional */
        }
    </style>

    <div class="py-8">
        <div class="slider-shell">
            <div class="slider-track" id="dashSlider">
                @foreach($slides as $i => $slide)
                    <div class="slide {{ $i === 0 ? 'is-active' : '' }}">
                        <img src="{{ $slide['src'] }}" alt="slide {{ $i+1 }}">
                    </div>
                @endforeach

                <div class="slider-nav">
                    <button type="button" class="slider-btn" data-dir="prev">&#10094;</button>
                    <button type="button" class="slider-btn" data-dir="next">&#10095;</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const slider = document.getElementById('dashSlider');
            const slides = Array.from(slider.querySelectorAll('.slide'));
            const arrows = Array.from(slider.querySelectorAll('.slider-btn'));

            const INTERVAL_MS = 5000; // 5 segundos
            let current = 0;
            let timer = null;

            function showSlide(index) {
                if (!slides.length) return;

                if (index < 0) index = slides.length - 1;
                if (index >= slides.length) index = 0;

                slides.forEach((s, i) => {
                    s.classList.toggle('is-active', i === index);
                });

                current = index;
            }

            function startAuto() {
                stopAuto();
                timer = setInterval(() => {
                    showSlide(current + 1);
                }, INTERVAL_MS);
            }

            function stopAuto() {
                if (timer) {
                    clearInterval(timer);
                    timer = null;
                }
            }

            // Flechas: navegación manual
            arrows.forEach(btn => {
                btn.addEventListener('click', () => {
                    const dir = btn.dataset.dir === 'prev' ? -1 : 1;
                    showSlide(current + dir);
                    startAuto(); // reinicia el temporizador
                });
            });

            // Opcional: pausar al pasar el mouse
            slider.addEventListener('mouseenter', stopAuto);
            slider.addEventListener('mouseleave', startAuto);

            showSlide(0);
            startAuto();
        });
    </script>
</x-app-layout>
