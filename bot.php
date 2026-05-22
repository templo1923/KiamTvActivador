<?php
// bot.php (Cerebro del Bot de KiamTv - VERSIÓN ULTRA PRO)

$token = "8705875952:AAGHKIesASCyVe5vn3v08Y7kAl4HUYtT_QI";
$api_url = "https://api.telegram.org/bot" . $token;
// PON AQUÍ TU ID PERSONAL PARA RECIBIR ALERTAS DE VENTAS (Ej: 5953193231)
$mi_id_admin = "5953193231"; 

$entrada = file_get_contents("php://input");
$update = json_decode($entrada, TRUE);

// 🧠 DICCIONARIO DE PREGUNTAS FRECUENTES
$preguntas_frecuentes = [
    "como instalo" => "Para instalar la app, ve a la tienda de tu TV, descarga 'Downloader' e ingresa este código: 12345.",
    "pantalla negra" => "Si la pantalla se queda negra, desconecta tu TV de la corriente por 1 minuto y reinicia tu router.",
    "no funciona" => "Por favor, verifica que tu TV esté conectada a internet. Si el problema persiste, solicita ayuda.",
    "que precio" => "Nuestros planes VIP son muy económicos. Toca el botón de 'Comprar VIP' en el /menu."
];

function enviarMensaje($url, $datos) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($datos));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $respuesta = curl_exec($ch);
    curl_close($ch);
    return $respuesta;
}

// =============================================================
// CAPTURA DE BOTONES INTERACTIVOS (CALLBACKS)
// =============================================================
if (isset($update["callback_query"])) {
    $callback_id = $update["callback_query"]["id"];
    $chatId = $update["callback_query"]["message"]["chat"]["id"];
    $datos_boton = $update["callback_query"]["data"];

    if ($datos_boton === "mostrar_tutoriales") {
        $texto_tuto = "📺 *TUTORIALES KIAMTV*\n\n1️⃣ Abre la app en tu TV.\n2️⃣ En la pantalla principal verás un código parecido a A1:B2:C3:D4...\n3️⃣ Esa es tu MAC.\n\nMira este video explicativo:\n👉 [ENLACE A TU VIDEO]";
        enviarMensaje($api_url . "/sendMessage", ['chat_id' => $chatId, 'text' => $texto_tuto, 'parse_mode' => 'Markdown']);
        enviarMensaje($api_url . "/answerCallbackQuery", ['callback_query_id' => $callback_id]);
    }
    exit;
}

// =============================================================
// CAPTURA DE TEXTO NORMAL
// =============================================================
if (isset($update["message"])) {
    $chatId = $update["message"]["chat"]["id"];
    $messageId = $update["message"]["message_id"]; 
    $tipo_chat = $update["message"]["chat"]["type"]; 
    $mensaje = $update["message"]["text"] ?? "";
    $nombre = $update["message"]["from"]["first_name"] ?? "Usuario";
    $userId = $update["message"]["from"]["id"];
    $username = $update["message"]["from"]["username"] ?? "Sin_Usuario";

    // 🗃️ BASE DE DATOS SILENCIOSA (Guarda a los clientes del chat privado)
    if ($tipo_chat === "private") {
        $archivo_clientes = "clientes.txt";
        $clientes_actuales = file_exists($archivo_clientes) ? file($archivo_clientes, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
        if (!in_array($chatId, $clientes_actuales)) {
            file_put_contents($archivo_clientes, $chatId . PHP_EOL, FILE_APPEND);
        }
    }

    // 🛡️ 1. ESCUDO ANTI-SPAM
    if ($tipo_chat === "group" || $tipo_chat === "supergroup") {
        if (preg_match('/(http|https|t\.me|www\.)/i', $mensaje)) {
            $url_admin = $api_url . "/getChatMember?chat_id=" . $chatId . "&user_id=" . $userId;
            $respuesta_admin = json_decode(file_get_contents($url_admin), TRUE);
            $estado_usuario = $respuesta_admin["result"]["status"] ?? "member";

            if ($estado_usuario === "member" || $estado_usuario === "restricted") {
                enviarMensaje($api_url . "/deleteMessage", ['chat_id' => $chatId, 'message_id' => $messageId]);
                enviarMensaje($api_url . "/sendMessage", ['chat_id' => $chatId, 'text' => "⚠️ @$nombre, enlaces externos no permitidos."]);
                exit; 
            }
        }
    }

    // 🌟 2. SALUDO INTELIGENTE Y BORRADO CÍCLICO
    if (isset($update["message"]["new_chat_members"])) {
        enviarMensaje($api_url . "/deleteMessage", ['chat_id' => $chatId, 'message_id' => $messageId]);

        foreach ($update["message"]["new_chat_members"] as $nuevoUsuario) {
            if (isset($nuevoUsuario["is_bot"]) && $nuevoUsuario["is_bot"]) continue; 

            if (file_exists("ultimo_saludo.txt")) {
                $id_viejo = file_get_contents("ultimo_saludo.txt");
                enviarMensaje($api_url . "/deleteMessage", ['chat_id' => $chatId, 'message_id' => $id_viejo]);
            }

            $nombreNuevo = $nuevoUsuario["first_name"];
            $textoBienvenida = "🚀 ¡Bienvenido a la comunidad KiamTv, $nombreNuevo!\n\nDisfruta de la mejor televisión sin cortes. Para activar tu prueba 24 Horas o descargar la App, toca el botón de abajo 👇";
            
            $tecladoBienvenida = json_encode([
                "inline_keyboard" => [[["text" => "🤖 Ir al Asistente VIP", "url" => "https://t.me/KiamTv_bot?start=bienvenida"]]]
            ]);

            $respuesta_telegram = enviarMensaje($api_url . "/sendMessage", ['chat_id' => $chatId, 'text' => $textoBienvenida, 'reply_markup' => $tecladoBienvenida]);
            $datos_respuesta = json_decode($respuesta_telegram, TRUE);

            if (isset($datos_respuesta['result']['message_id'])) {
                file_put_contents("ultimo_saludo.txt", $datos_respuesta['result']['message_id']);
            }
        }
    }

    // 🤖 3. LECTURA DEL FAQ DINÁMICO
    if ($tipo_chat === "group" || $tipo_chat === "supergroup") {
        $mensaje_limpio = strtolower($mensaje);
        foreach ($preguntas_frecuentes as $palabra_clave => $respuesta_guardada) {
            if (strpos($mensaje_limpio, $palabra_clave) !== false && strpos($mensaje, "/") !== 0) {
                enviarMensaje($api_url . "/sendMessage", ['chat_id' => $chatId, 'text' => "Hola @$nombre 🛠️\n\n" . $respuesta_guardada]);
                break; 
            }
        }
    }

    // 🎛️ 4. LA LÓGICA DEL TECLADO FIJO
    if ($mensaje === "/teclado") {
        $teclado_flotante = json_encode([
            "keyboard" => [
                [["text" => "🎁 Activar 24 Hrs"], ["text" => "📥 Descargar App"]],
                [["text" => "💎 Comprar VIP"], ["text" => "📺 Tutoriales"]]
            ],
            "resize_keyboard" => true,
            "is_persistent" => true
        ]);
        enviarMensaje($api_url . "/sendMessage", ['chat_id' => $chatId, 'text' => "✅ Teclado fijo instalado con éxito.", 'reply_markup' => $teclado_flotante]);
    }

    if ($mensaje === "🎁 Activar 24 Hrs") {
        $teclado_link = json_encode(["inline_keyboard" => [[["text" => "🔗 Portal de Activación", "url" => "https://catalogosco.top/KiamTv/activar.html"]]]]);
        enviarMensaje($api_url . "/sendMessage", ['chat_id' => $chatId, 'text' => "Hola @$nombre 🎁\nActiva tu prueba ingresando tu MAC aquí 👇", 'reply_markup' => $teclado_link]);
    }
    
    if ($mensaje === "📥 Descargar App") {
        $teclado_link = json_encode(["inline_keyboard" => [[["text" => "⬇️ Descargar APK", "url" => "https://google.com"]]]]);
        enviarMensaje($api_url . "/sendMessage", ['chat_id' => $chatId, 'text' => "Hola @$nombre 📥\nDescarga la última versión de KiamTv aquí 👇", 'reply_markup' => $teclado_link]);
    }

    if ($mensaje === "💎 Comprar VIP") {
        $teclado_link = json_encode(["inline_keyboard" => [[["text" => "🤖 Asistente VIP", "url" => "https://t.me/KiamTv_bot?start=vip"]]]]);
        enviarMensaje($api_url . "/sendMessage", ['chat_id' => $chatId, 'text' => "Hola @$nombre 💎\nPara ver planes y precios, toca el botón para ir al privado 👇", 'reply_markup' => $teclado_link]);
    }

    if ($mensaje === "📺 Tutoriales") {
        $texto_tuto = "📺 *TUTORIALES KIAMTV*\n\n1️⃣ Abre la app en tu TV.\n2️⃣ Busca el código MAC en la pantalla.\nMira este video explicativo:\n👉 [ENLACE A TU VIDEO]";
        enviarMensaje($api_url . "/sendMessage", ['chat_id' => $chatId, 'text' => $texto_tuto, 'parse_mode' => 'Markdown']);
    }

    // 📱 5. COMANDOS PÚBLICOS (/menu) Y PRIVADOS (/start)
    if (strpos($mensaje, "/menu") === 0 || strpos($mensaje, "/start") === 0) {
        if (trim($mensaje) === "/start" || trim($mensaje) === "/menu" || strpos($mensaje, "@") !== false) {
            
            $texto = "¡Hola $nombre! Soy tu asistente virtual 🤖\n\nSelecciona una opción 👇";
            
            // NUEVO: El menú privado y público ahora incluye el botón de unirse al grupo
            $teclado = json_encode([
                "inline_keyboard" => [
                    [["text" => "🎁 Activar 24 Horas", "url" => "https://catalogosco.top/KiamTv/activar.html"]],
                    [["text" => "📥 Descargar App", "url" => "https://google.com"]], 
                    [["text" => "💎 Comprar VIP", "url" => "https://t.me/KiamTv_bot?start=vip"]],
                    [["text" => "📺 Tutoriales", "callback_data" => "mostrar_tutoriales"]],
                    [["text" => "👥 Únete a nuestro Grupo Oficial", "url" => "https://t.me/TU_ENLACE_DEL_GRUPO_AQUI"]]
                ]
            ]);
            enviarMensaje($api_url . "/sendMessage", ['chat_id' => $chatId, 'text' => $texto, 'reply_markup' => $teclado]);
        }
    }

    // 🔒 6. EMBUDO DE VENTAS (Comandos Redireccionados)
    if (strpos($mensaje, "/start bienvenida") === 0 && $tipo_chat === "private") {
        $texto = "¡Hola $nombre! Qué gusto tenerte por aquí 🔒\n\nDesde aquí te daré soporte más rápido y directo. ¿Qué te gustaría hacer ahora mismo? 👇";
        $teclado = json_encode([
            "inline_keyboard" => [
                [["text" => "🎁 Activar 24 Horas", "url" => "https://catalogosco.top/KiamTv/activar.html"]],
                [["text" => "📥 Descargar App", "url" => "https://google.com"]], 
                [["text" => "💎 Comprar VIP", "url" => "https://t.me/KiamTv_bot?start=vip"]],
                [["text" => "📺 Tutoriales", "callback_data" => "mostrar_tutoriales"]],
                [["text" => "👥 Ir al Grupo Público", "url" => "https://t.me/TU_ENLACE_DEL_GRUPO_AQUI"]]
            ]
        ]);
        enviarMensaje($api_url . "/sendMessage", ['chat_id' => $chatId, 'text' => $texto, 'reply_markup' => $teclado]);
    }

    if (strpos($mensaje, "/start vip") === 0 && $tipo_chat === "private") {
        $texto = "💎 *PLANES VIP KIAMTV*\n\nDisfruta del mejor contenido sin interrupciones.\n\n🔹 *1 Mes:* $XX.XXX\n🔹 *3 Meses:* $YY.YYY\n\nPuedes realizar tu pago seguro por Wompi aquí:\n👉 [ENLACE DE WOMPI AQUI]\n\nO si prefieres, escríbele directo a nuestro asesor de ventas:\n👉 [ENLACE DE TU WHATSAPP]";
        enviarMensaje($api_url . "/sendMessage", ['chat_id' => $chatId, 'text' => $texto, 'parse_mode' => 'Markdown']);
        
        // 🔥 ALERTA ADMIN: Te notifica silenciosamente que alguien vio el VIP
        if ($mi_id_admin !== "5953193231") {
            $alerta_texto = "🤑 *ALERTA DE VENTA*\nEl usuario [$nombre](tg://user?id=$userId) (Usuario: @$username) acaba de solicitar información de los planes VIP.";
            enviarMensaje($api_url . "/sendMessage", ['chat_id' => $mi_id_admin, 'text' => $alerta_texto, 'parse_mode' => 'Markdown']);
        }
    }
}
?>