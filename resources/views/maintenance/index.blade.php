@if(($mode ?? 'default') === 'custom_html' && !empty($customHtml))
    {!! $customHtml !!}
@else
@php
    $title = $title ?? 'David Bravo';
    $logoText = $logoText ?? '>_db';
    $accentColor = $accentColor ?? '#00FF99';
    $bgColor = $bgColor ?? '#1A1A1A';
    $parts = explode('_', $logoText, 2);
    $prefix = $parts[0] ?? '>';
    $hasUnderscore = count($parts) > 1;
    $suffix = $parts[1] ?? 'db';

    // Parse hex to rgba
    $cleanHex = ltrim($accentColor, '#');
    if (strlen($cleanHex) === 3) {
        $r = hexdec(substr($cleanHex, 0, 1) . substr($cleanHex, 0, 1));
        $g = hexdec(substr($cleanHex, 1, 1) . substr($cleanHex, 1, 1));
        $b = hexdec(substr($cleanHex, 2, 1) . substr($cleanHex, 2, 1));
    } elseif (strlen($cleanHex) === 6) {
        $r = hexdec(substr($cleanHex, 0, 2));
        $g = hexdec(substr($cleanHex, 2, 2));
        $b = hexdec(substr($cleanHex, 4, 2));
    } else {
        $r = 0; $g = 255; $b = 153;
    }
    $accentRgba = "rgba({$r}, {$g}, {$b}, 0.5)";
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>

    <style>
        :root {
            --accent-color: {{ $accentColor }};
            --accent-rgba: {{ $accentRgba }};
            --bg-color: {{ $bgColor }};
        }

        body,
        html {
            height: 100%;
            margin: 0;
            background-color: var(--bg-color);
            font-family: 'Google Sans Code', monospace;
            overflow: hidden;
        }

        .main-container {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100vh;
            gap: 28px;
            position: relative;
            z-index: 1;
        }

        .logo-display {
            font-size: clamp(3.5rem, 10vw, 7rem);
            font-weight: 700;
            color: #FFFFFF;
            cursor: default;
            user-select: none;
            text-align: center;
            letter-spacing: -2px;
        }

        .cursor-blink {
            color: var(--accent-color);
            display: inline-block;
            animation: blink 1s step-end infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }

        .btn {
            padding: 12px 24px;
            background-color: transparent;
            border: 2px solid var(--accent-color);
            color: var(--accent-color);
            font-family: 'Google Sans Code', monospace;
            font-weight: 700;
            font-size: 0.9rem;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            z-index: 2;
        }

        .btn:hover {
            background-color: var(--accent-color);
            color: var(--bg-color);
            box-shadow: 0 0 20px var(--accent-rgba);
        }

        .btn:active {
            transform: scale(0.98);
        }

        .countdown-card {
            text-align: center;
            padding: 12px 24px;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(4px);
        }

        .countdown-label {
            font-size: 0.7rem;
            color: var(--accent-color);
            letter-spacing: 2px;
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .countdown-digits {
            font-size: clamp(1rem, 3vw, 1.4rem);
            font-weight: 700;
            color: #FFFFFF;
            letter-spacing: 2px;
        }

        .status-message {
            max-width: 540px;
            text-align: center;
            color: rgba(255, 255, 255, 0.65);
            font-size: 0.85rem;
            line-height: 1.6;
            padding: 0 20px;
            margin: 0;
        }

        #canvas {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 0;
            pointer-events: none;
        }

        .admin-link {
            position: fixed;
            bottom: 16px;
            right: 16px;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.3);
            text-decoration: none;
            z-index: 10;
            transition: all 0.2s ease;
            font-family: 'Google Sans Code', monospace;
            padding: 4px 8px;
            border-radius: 4px;
        }

        .admin-link:hover {
            color: var(--accent-color);
            background: rgba(0, 0, 0, 0.6);
        }
    </style>
</head>

<body>
    <div class="main-container">
        <div class="logo-display">
            <span>{{ $prefix }}</span>@if($hasUnderscore)<span class="cursor-blink">_</span><span>{{ $suffix }}</span>@endif
        </div>

        @if(!empty($headline) || !empty($message))
            <p class="status-message">
                {{ $message ?: $headline }}
            </p>
        @endif

        @if(!empty($showCountdown) && !empty($endDateTime))
            <div class="countdown-card" id="countdown-wrapper" data-target="{{ $endDateTime->toIso8601String() }}">
                <div class="countdown-label">
                    <span>[ TIEMPO PARA REAPERTURA ]</span>
                </div>
                <div class="countdown-digits" id="countdown-display">
                    Calculando...
                </div>
            </div>
        @endif

        @if(!empty($showDownloadSvg))
            <button id="downloadSvgBtn" class="btn">Download SVG</button>
        @endif

        <canvas id="canvas" width="1920" height="927"></canvas>
    </div>

    @if(!empty($showAdminLink))
        <a href="{{ route('admin.login') }}" class="admin-link">Acceso Técnico &rarr;</a>
    @endif

    @if(!empty($showDownloadSvg))
    <script nonce="{{ $cspNonce ?? '' }}">
        document.getElementById('downloadSvgBtn').addEventListener('click', function () {
            const bgColor = "{{ $bgColor }}";
            const textColor = "#FFFFFF";
            const accentColor = "{{ $accentColor }}";
            const fontMain = "'Google Sans Code', monospace";
            const fontUrlEscaped = "https://fonts.googleapis.com/css2?family=Google+Sans+Code:wght@700&amp;display=swap";
            const svgContent = `
<svg xmlns="http://www.w3.org/2000/svg" width="600" height="200" viewBox="0 0 600 200">
    <defs>
        <style type="text/css">
            @import url('${fontUrlEscaped}');
        </style>
    </defs>
    <rect width="100%" height="100%" fill="${bgColor}"/>
    <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="${fontMain}" font-weight="bold" font-size="120">
        <tspan fill="${textColor}">{{ $prefix }}</tspan><tspan fill="${accentColor}">_</tspan><tspan fill="${textColor}">{{ $suffix }}</tspan>
    </text>
</svg>`.trim();

            const blob = new Blob([svgContent], { type: 'image/svg+xml;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const downloadLink = document.createElement('a');
            downloadLink.href = url;
            downloadLink.download = 'david-bravo-logo-dark.svg';
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
            URL.revokeObjectURL(url);
        });
    </script>
    @endif

    @if(!empty($showCountdown) && !empty($endDateTime))
    <script nonce="{{ $cspNonce ?? '' }}">
        (function() {
            const wrapper = document.getElementById('countdown-wrapper');
            const display = document.getElementById('countdown-display');
            if (!wrapper || !display) return;

            const targetTime = new Date(wrapper.getAttribute('data-target')).getTime();

            function updateCountdown() {
                const now = new Date().getTime();
                const distance = targetTime - now;

                if (distance < 0) {
                    display.innerText = "¡ESTAMOS DE VUELTA!";
                    return;
                }

                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                const pad = n => String(n).padStart(2, '0');
                display.innerText = `${days}d : ${pad(hours)}h : ${pad(minutes)}m : ${pad(seconds)}s`;
            }

            updateCountdown();
            setInterval(updateCountdown, 1000);
        })();
    </script>
    @endif

    <script nonce="{{ $cspNonce ?? '' }}">
        function Polygon(context, options) {
            var defaults = {
                numberOfVertices: 3,
                size: 20,
                centerPosition: {
                    x: 0,
                    y: 0
                },
                line: {
                    color: 'rgba(255, 255, 255, .5)',
                    width: 1
                }
            },
            config = { ...defaults, ...options };

            this.setContext = function () {
                context.strokeStyle = config.line.color;
                context.lineWidth = config.line.width;
            };

            this.createPolygon = function () {
                var centerX = config.centerPosition.x;
                var centerY = config.centerPosition.y;
                var nV = config.numberOfVertices;
                var rotate = (Math.PI - (2 * Math.PI) / nV) / 2;
                var size = config.size;
                var currentCenterX = centerX + size * Math.cos(rotate);
                var currentCenterY = centerY + size * Math.sin(rotate);
                context.beginPath();
                context.moveTo(currentCenterX, currentCenterY);
                for (var i = 1; i <= nV; i++) {
                    var sLine = centerX + size * Math.cos(rotate + (i * 2 * Math.PI / nV));
                    var endLine = centerY + size * Math.sin(rotate + (i * 2 * Math.PI / nV));
                    context.lineTo(sLine, endLine);
                }
                context.closePath();
                context.stroke();
            };

            this.init = function () {
                this.setContext();
                this.createPolygon();
            };
        }

        var canvas = document.getElementById('canvas'),
            ctx = canvas.getContext('2d');

        function resizeCanvas() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            if (currentPolygon) {
                currentPolygon.centerPosition = {
                    x: canvas.width / 2,
                    y: canvas.height / 2
                };
            }
        }

        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();

        var startPolygon = {
            size: 350,
            numberOfVertices: 3,
            line: {
                color: '{{ $accentRgba }}',
                width: 1
            },
            centerPosition: {
                x: canvas.width / 2,
                y: canvas.height / 2
            }
        };

        var currentPolygon = startPolygon;

        var fps = 5,
            fpsInterval = 1000 / fps,
            then = Date.now(),
            startTime = then;

        var addVertex = true;
        var rafId = null;

        // Respect accessibility preferences and stop burning CPU when hidden.
        var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        function renderFrame() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            if (currentPolygon.numberOfVertices >= 100 && addVertex)
                addVertex = false;
            if (currentPolygon.numberOfVertices <= 3 && !addVertex)
                addVertex = true;

            var polygon = new Polygon(ctx, currentPolygon);
            polygon.init();

            if (addVertex)
                currentPolygon.numberOfVertices += 1;
            else
                currentPolygon.numberOfVertices -= 1;
            fps = currentPolygon.numberOfVertices;
            fpsInterval = 1000 / fps;
        }

        function drawPolygon() {
            var now = Date.now();
            var elapsed = now - then;
            if (elapsed > fpsInterval) {
                then = now - (elapsed % fpsInterval);
                renderFrame();
            }

            rafId = window.requestAnimationFrame(drawPolygon);
        }

        function stopCanvas() {
            if (rafId === null) return;
            window.cancelAnimationFrame(rafId);
            rafId = null;
        }

        function startCanvas() {
            if (rafId !== null) return;
            then = Date.now();
            rafId = window.requestAnimationFrame(drawPolygon);
        }

        if (reduceMotion) {
            // Single static frame for visitors who opt out of motion.
            renderFrame();
            window.addEventListener('resize', renderFrame);
        } else {
            startCanvas();
            document.addEventListener('visibilitychange', function () {
                if (document.hidden) {
                    stopCanvas();
                } else {
                    startCanvas();
                }
            });
        }
    </script>
</body>
</html>
@endif
