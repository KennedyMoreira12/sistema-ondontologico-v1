<?php

$base   = 'http://127.0.0.1:8000';
$pac    = 1;
$cookie = sys_get_temp_dir() . '/planos-cj.txt';
@unlink($cookie);

function call($metodo, $url, $dados = null, bool $redir = false)
{
    global $cookie;
    $ch = curl_init($url);
    $opcoes = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => $redir,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_SSL_VERIFYPEER => false,
    ];
    if ($metodo !== 'GET') {
        $opcoes[CURLOPT_CUSTOMREQUEST] = $metodo;
        if ($dados !== null) {
            $opcoes[CURLOPT_POSTFIELDS] = http_build_query($dados);
        }
    }
    curl_setopt_array($ch, $opcoes);
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $urlFinal = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch deaths);

    if (str_contains($raw, "\r\n\r\n")) {
        [$cabecalho, $corpo] = explode("\r\n\r\n", $raw, 2);
    } else {
        $cabecalho = '';
        $corpo = $raw;
    }
    $local = '';
    if (preg_match('/^Location: (.+)$/mi', $cabecalho, $m)) { $local = trim($m[1]); }

    return ['status' => $status, 'html' => $corpo, 'url' => $urlFinal, 'loc' => $local];
}

function tokenDe($html)
{
    if (preg_match('/name="_token" value="([^"]+)"/', $html, $m)) { return $m[1]; }
    if (preg_match("/name='_token' value='([^']+)'/", $html, $m)) { return $m[1]; }
    return '';
}

// login admin
$r = call('GET', "$base/login");
$ok = call('POST', "$base/login", ['_token' => tokenDe($r['html']), 'email' => 'admin@sco.com', 'password' => 'admin123']);
echo "LOGIN -> {$ok['status']} (302)\n";

// index planos
$r = call('GET', "$base/pacientes/$pac/planos");
echo "INDEX -> {$r['status']} | botao 'Novo plano'? -> " . (str_contains($r['html'], 'Novo plano') ? 'S' : 'N') . "\n";
$tok = tokenDe($r['html']);

// criar plano
$resp = call('POST', "$base/pacientes/$pac/planos", ['_token' => $tok, 'titulo' => 'Reabilitacao parcial', 'descricao' => 'Limpeza e restauracao', 'desconto' => '15']);
echo "CRIAR PLANO -> {$resp['status']} (302) | redirect? -> " . (preg_match('#/planos/(\d+)#', $resp['loc'] ?? '', $m) ? 'S (' . $m[1] . ')' : 'N:' . ($resp['loc'] ?? '?')) . "\n";
$planoId = isset($m) ? $m[1] : '';

// show
$r = call('GET', "$base/pacientes/$pac/planos/$planoId");
echo "SHOW -> {$r['status']} | titulo-> " . (str_contains($r['html'], 'Reabilitacao parcial') ? 'S' : 'N')
   . " | badge Rascunho-> " . (str_contains($r['html'], 'Rascunho') ? 'S' : 'N')
   . " | total 0-> " . (preg_match('/R\$ 0,00/', $r['html']) ? 'S' : 'N')
   . " | breadcrumb volta p/ lista? -> " . (str_contains($r['html'], 'Planos de tratamento') ? 'S' : 'N') . "\n";
$tok = tokenDe($r['html']);

// adicionar item (Limpeza, proc 1, dente 16, valor 150)
$resp = call('POST', "$base/pacientes/$pac/planos/$planoId/itens", ['_token' => $tok, 'procedure_id' => '1', 'dente' => '16', 'quantidade' => '1', 'valor_unitario' => '150', 'desconto' => '0', 'observacao' => 'Profilaxia completa']);
echo "ADD ITEM -> {$resp['status']} (302)\n";
$r = call('GET', "$base/pacientes/$pac/planos/$planoId");
echo "tabela tem Limpeza? -> " . (str_contains($r['html'], 'Limpeza') ? 'S' : 'N')
   . " | obs? -> " . (str_contains($r['html'], 'Profilaxia completa') ? 'S' : 'N')
   . " | subtotal 150? -> " . (preg_match('/R\$ 150,00/', $r['html']) ? 'S' : 'N')
   . " | total plano 135 (150-15)? -> " . (str_contains($r['html'], 'R$ 135,00') ? 'S' : 'N') . "\n";

// dente inválido 99 -> deve falhar validação (redirect com erro, sem salvar)
$r = call('GET', "$base/pacientes/$pac/planos/$planoId");
$tok = tokenDe($r['html']);
$resp = call('POST', "$base/pacientes/$pac/planos/$planoId/itens", ['_token' => $tok, 'procedure_id' => '1', 'dente' => '99', 'quantidade' => '1', 'valor_unitario' => '150', 'desconto' => '0', 'observacao' => '']);
$r = call('GET', "$base/pacientes/$pac/planos/$planoId");
echo "ADD DENTE 99 -> {$resp['status']} (302) | item NAO entrou? -> " . (str_contains($r['html'], 'Dente 99') ? 'N(bug)' : 'S') . "\n";

// executar item
$r = call('GET', "$base/pacientes/$pac/planos/$planoId");
$tok = tokenDe($r['html']);
$resp = call('PATCH', "$base/pacientes/$pac/planos/$planoId/itens/1/status", ['_token' => $tok, 'status' => 'executado']);
echo "EXECUTAR ITEM -> {$resp['status']} (302)\n";
$r = call('GET', "$base/pacientes/$pac/planos/$planoId");
echo "item Executado? -> " . (str_contains($r['html'], 'Executado') ? 'S' : 'N') . " | continua somando no total? -> " . (str_contains($r['html'], 'R$ 135,00') ? 'S' : 'N') . "\n";

// aprovar plano (travas edição)
$r = call('GET', "$base/pacientes/$pac/planos/$planoId");
$tok = tokenDe($r['html']);
$resp = call('PATCH', "$base/pacientes/$pac/planos/$planoId/status", ['_token' => $tok, 'status' => 'aprovado']);
echo "APROVAR PLANO -> {$resp['status']} (302)\n";
$r = call('GET', "$base/pacientes/$pac/planos/$planoId");
echo "badge Aprovado? -> " . (str_contains($r['html'], 'Aprovado') ? 'S' : 'N')
   . " | form adicionar proc some? -> " . (str_contains($r['html'], 'Adicionar procedimento') ? 'N(bug)' : 'S')
   . " | opcoes de status some? -> " . (str_contains($r['html'], 'Mover para') ? 'N(bug)' : 'S') . "\n";

// editar item (qtd -> 2, valor 150, dente 16) subtotal 300 - desconto 15 = 285
$r = call('GET', "$base/pacientes/$pac/planos/$planoId");
if (preg_match('#/itens/(\d+)/status#', $r['html'], $m2)) { $itemId = $m2[1]; } else { $itemId = '1'; }
$resp = call('PUT', "$base/pacientes/$pac/planos/$planoId/itens/$itemId", ['_token' => tokenDe($r['html']), 'procedure_id' => '1', 'dente' => '16', 'quantidade' => '2', 'valor_unitario' => '150', 'desconto' => '0', 'observacao' => '']);
echo "EDITAR ITEM (qtd 2) -> {$resp['status']} (302)\n";
$r = call('GET', "$base/pacientes/$pac/planos/$planoId");
echo "total 285 (2x150-15)? -> " . (str_contains($r['html'], 'R$ 285,00') ? 'S' : 'N') . "\n";

// excluir item -> volta a 0 (com desconto 15 restante... 0-15 -> max 0)
$r = call('GET', "$base/pacientes/$pac/planos/$planoId");
$resp = call('DELETE', "$base/pacientes/$pac/planos/$planoId/itens/$itemId", ['_token' => tokenDe($r['html'])]);
echo "EXCLUIR ITEM -> {$resp['status']} (302)\n";
$r = call('GET', "$base/pacientes/$pac/planos/$planoId");
echo "item sumiu? -> " . (str_contains($r['html'], 'Limpeza') ? 'N(bug)' : 'S') . " | total 0? -> " . (str_contains($r['html'], 'R$ 0,00') ? 'S' : 'N') . "\n";

echo "FIM smoke planos\n";