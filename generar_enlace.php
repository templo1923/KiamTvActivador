<?php
// generar_enlace.php (EN CATALOGOSCO.TOP)

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mac_address'])) {
    
    // 1. Recibir los datos del formulario
    $mac = strtoupper(preg_replace('/[^0-9A-F:]/', '', $_POST['mac_address']));
    $contacto = urlencode(trim($_POST['whatsapp'] ?? '')); // Ojo: Aqu¨ª cambi¨¦ a 'whatsapp' para que coincida con tu HTML

    // 2. Armar la URL de tu servidor backend en tucentral.store
    $url_destino = "https://tucentral.store/ibo/ibov5/activar_freemium.php?mac_address={$mac}&whatsapp={$contacto}";

    // 3. Tu API Token de Shortig
    $api_token = "a8444ebe-4d7d-11f1-a767-0200fd828666"; 
    $url_codificada = urlencode($url_destino);

    // 4. Llamar a Shortig
    $api_url = "https://shortig.com/api.php?api={$api_token}&url={$url_codificada}&yeslinktext=yes";
    $short_url = @file_get_contents($api_url);

    // 5. Redirigir
    if ($short_url && filter_var(trim($short_url), FILTER_VALIDATE_URL)) {
        header("Location: " . trim($short_url));
        exit();
    } else {
        // Si Shortig falla, te mandamos directo a tu archivo final
        header("Location: " . $url_destino);
        exit();
    }

} else {
    echo "<h1>Error</h1><p>Acceso denegado. Utiliza el formulario principal.</p>";
}
?>