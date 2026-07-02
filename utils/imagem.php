<?php
/**
 * Helpers de imagem.
 *
 * normalizar_foto_base64(): corrige a orientação EXIF de fotos JPEG vindas
 * de celular. Câmeras gravam os pixels "deitados" + uma tag EXIF Orientation
 * dizendo como exibir. Navegadores respeitam a tag (foto aparece certa), mas
 * os dispositivos faciais Intelbras recebem os pixels crus — rosto de lado =
 * detecção falha e o aparelho recusa a foto ("foto falhou" no sync).
 *
 * A função rotaciona os pixels conforme a tag e re-encoda o JPEG (o que
 * remove o EXIF), devolvendo uma imagem que aparece igual em todo lugar.
 * Em qualquer falha (não é JPEG, sem EXIF, GD indisponível), devolve o
 * base64 original sem mexer — nunca quebra o fluxo de upload.
 *
 * @param string $b64 imagem em base64 SEM o prefixo "data:image/...;base64,"
 * @return string base64 normalizado (ou o original, se não precisou/conseguiu)
 */
function normalizar_foto_base64(string $b64): string
{
    if ($b64 === '' || !function_exists('imagecreatefromstring')) {
        return $b64;
    }

    $bin = base64_decode($b64, true);
    if ($bin === false || strlen($bin) < 64) {
        return $b64;
    }

    $info = @getimagesizefromstring($bin);
    if (!$info || ($info['mime'] ?? '') !== 'image/jpeg') {
        // PNG/WebP não carregam EXIF Orientation — nada a fazer.
        return $b64;
    }

    // exif_read_data exige arquivo/stream — usa wrapper em memória.
    $orientation = 1;
    if (function_exists('exif_read_data')) {
        $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($bin));
        $orientation = (int) ($exif['Orientation'] ?? 1);
    }
    if ($orientation <= 1 || $orientation > 8) {
        return $b64; // já está em pé (ou tag inválida)
    }

    $img = @imagecreatefromstring($bin);
    if (!$img) {
        return $b64;
    }

    // Espelhamentos (2,4,5,7) e rotações (3,6,8).
    // imagerotate: ângulo positivo = anti-horário.
    switch ($orientation) {
        case 2: imageflip($img, IMG_FLIP_HORIZONTAL); break;
        case 3: $img = imagerotate($img, 180, 0); break;
        case 4: imageflip($img, IMG_FLIP_VERTICAL); break;
        case 5: imageflip($img, IMG_FLIP_HORIZONTAL); $img = imagerotate($img, -90, 0); break;
        case 6: $img = imagerotate($img, -90, 0); break;
        case 7: imageflip($img, IMG_FLIP_HORIZONTAL); $img = imagerotate($img, 90, 0); break;
        case 8: $img = imagerotate($img, 90, 0); break;
    }
    if (!$img) {
        return $b64;
    }

    ob_start();
    $ok = imagejpeg($img, null, 92); // re-encode remove o EXIF
    $novo = ob_get_clean();
    imagedestroy($img);

    return ($ok && $novo !== false && $novo !== '') ? base64_encode($novo) : $b64;
}
