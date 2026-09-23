<?php
// ===== SMOKE HTTP FINAL — módulo Planos de Tratamento =====
$base   = 'http://127.0.0.1:8000';
$pac    = '1';
$cookie = sys_get_temp_dir().'/sco-planos-final.txt';
@unlink($cookie);

function req(string $metodo, string $url, ?array $dados = null, bool $redir = false): array {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => $redir,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_COOKIEJAR => $GLOBALS['cookie'],
        CURLOPT_COOKIEFILE => $GLOBALS['cookie'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 20,
    ];
    if ($metodo !== 'GET') {
        $opts[CURLOPT_CUSTOMREQUEST] = strtoupper($metodo);
        if ($dados !== null) { $opts[CURLOPT_POSTFIELDS] = http_build_query($dados); }
    }
    curl_setopt_array($ch, $opts);
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $eff = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch | ...);
    // nunca acaba aqui

    $html = $raw; $loc = '';
    if (preg_match('/^Location: (.+)$/mi', $raw, $m)) { $loc = trim($m[1]); }
    if (str_contains($raw, "\r\n\r\n")) { [$h, $b] = explode("\r\n\r\n", $raw, 2); $html = $b; }
    return ['status' => $status, 'html' => $html, 'url' => $eff, 'loc' => $loc];
}

function tokenDe(string $html): string {
    if (preg_match('/name="_token" value="([^"]+)"/', $html, $m)) { return $m[1]; }
    if (preg_match("/name='_token' value='([^']+)'/", $html, $m)) { return $m[1]; }
    return '';
}

// LOGIN admin
$r = req('GET', "$base/login");
$resp = req('POST', "$base/login", ['_token' => tokenDe($r['html']), 'email' => 'admin@sco.com', 'password' => 'admin123']);
echo "LOGIN -> {$resp['status']} (302)\n";

// INDEX
$r = req('GET', "$base/pacientes/$pac/planos");
echo "INDEX -> {$r['status']} | botao 'Novo plano'? -> " . (str_contains($r['html'], 'Novo plano') ? 'S' : 'N')
   . " | msg vazio? -> " . (str_contains($r['html'], 'Nenhum plano de tratamento') ? 'S' : 'N') . "\n";
$tok = tokenDe($r['html']);

// CRIAR plano
$resp = req('POST', "$base/pacientes/$pac/planos", ['_token' => $tok, 'titulo' => 'Reabilitacao parcial', 'descricao' => 'Limpeza + restauracao', 'desconto' => '15']);
echo "CRIAR -> {$resp['status']} (302) | para show? -> " . (preg_match('#/planos/(\d+)$#', $resp['loc'], $m) ? 'S ('.$m[1].')' : 'N') . "\n";
$idPlano = preg_match('#/planos/(\d+)$#', $resp['loc'], $m) ? $m[1] : '';

// SHOW vazio
$r = req('GET', "$base/pacientes/$pac/planos/$idPlano");
echo "SHOW -> {$r['status']} | badge Rascunho? -> " . (str_contains($r['html'], 'Rascunho') ? 'S' : 'N')
   . " | total 0? -> " . (preg_match('/R\$ 0,00/', $r['html']) ? 'S' : 'N')
   . " | botao adicionar? -> " . (str_contains($r['html'], 'Adicionar procedimento') ? 'S' : 'N')
   . " | CSS print? -> " . (str_contains($r['html'], '@media print') ? 'S' : 'N')
   . " | botao imprimir? -> " . (str_contains($r['html'], 'Imprimir') ? 'S' : 'N') . "\n";
$tok = tokenDe($r['html']);

// ADD procedimento (Limpeza proc 1, valor 150, dente 16)
$resp = req('POST', "$base/pacientes/$pac/planos/$idPlano/itens", ['_token' => $tok, 'procedure_id' => '1', 'dente' => '16', 'quantidade' => '1', 'valor_unitario' => '', 'desconto' => '0', 'observacao' => 'Profilaxia completa']);
echo "ADD ITEM -> {$resp['status']} (302)\n";
$r = req('GET', "$base/pacientes/$pac/planos/$idPlano");
echo "Limpeza? -> " . (str_contains($r['html'], 'Limpeza') ? 'S' : 'N')
   . " | obs? -> " . (str_contains($r['html'], 'Profilaxia completa') ? 'S' : 'N')
   . " | subtotal 150? -> " . (str_contains($r['html'], 'R$ 150,00') ? 'S' : 'N')
   . " | total 135 (desc plano)? -> " . (str_contains($r['html'], 'R$ 135,00') ? 'S' : 'N') . "\n";
$tok = tokenDe($r['html']);

// dente invalido 99 -> redirect c erro, NÃO adiciona
$resp = req('POST', "$base/pacientes/$pac/planos/$idPlano/itens", ['_token' => $tok, 'procedurte_id' => '1', 'dente' => '99', 'quantidade' => '1', 'valor_unitario' => '100', 'desconto' => '0', 'observacao' => '']);
$r = req('GET', "$base/pacientes/$pac/planos/$idPlano");
echo "DENTE 99 entrou? -> " . (str_contains($r['html'], 'Dente 99') ? 'S(BUG)' : 'N') . " | Limpeza segue? -> " . (str_contains($r['html'], 'Limpeza') ? 'S' : 'N') . "\n";
$tok = tokenDe($r['html']);

// EXECUTAR item
$m = null;
if (preg_match('#itens/(\d+)/status#', $r['html'], $m)) { $idItem = $m[1]; }
$resp = req('PATCH', "$base/pacientes/$pac/planos/$idPlano/itens/$idItem/status", ['_token' => $tok, 'status' => 'executado']);
echo "EXECUTAR ITEM -> {$resp['status']} (302)\n";
$r = req('GET', "$base/pacientes/$pac/planos/$idPlano");
echo "badge Executado? -> " . (str_contains($r['html'], 'Executado') ? 'S' : 'N') . "\n";
$tok = tokenDe($r['html']);

// APROVAR plano
$resp = req('PATCH', "$base/pacientes/$pac/planos/$idPlano/status", ['_token' => $tok, 'status' => 'aprovado']);
echo "APROVAR -> {$resp['status']} (302)\n";
$r = req('GET', "$base/pacientes/$pac/planos/$idPlano");
echo "badge Aprovado? -> " . (str_contains($r['html'], 'Aprovado') ? 'S' : 'N')
   . " | form add some? -> " . (str_contains($r['html'], 'Adicionar procedimento') ? 'N(BUG)' : 'S') . "\n";
$tok = tokenDe($r['html']);

// EDITAR item (qtd 2, valor 150 -> subtotal 300, desc plano 15 -> total 285)
$m2 = null;
if (preg_match('#itens/(\d+)/edit#', $r['html'], $m2)) { $idItem = $m2[1]; }
$resp = req('PUT', "$base/pacientes/$pac/planos/$idPlano/itens/$idItem", ['_token' => $tok, 'procedurte_id' => '1', 'dente' => '16', 'quantidade' => '2', 'valor_unitario' => '150', 'desconto' => '0', 'observacao' => '']);
echo "EDITAR (qtd2) -> {$resp['status']} (302)\n";
$r = req('GET', "$base/pacientes/$pac/planos/$idPlano");
echo "total 285? -> " . (str_contains($r['html'], 'R$ 285,00') ? 'S' : 'N') . "\n";
$tok = tokenDe($r['html']);

// EXCLUIR item -> total volta a 0
$m3 = null;
if (preg_match('#itens/(\d+)/edit#', $r['html'], $m3)) { $idItem = $m3[1]; }
$resp = req('DELETE', "$base/pacientes/$pac/planos/$idPlano/itens/$idItem", ['_token' => $tok]);
echo "EXCLUIR ITEM -> {$resp['status']} (302)\n";
$r = req('GET', "$base/pacientes/$pac/planos/$idPlano");
echo "item sumiu? -> " . (str_contains($r['html'], 'Limpeza') ? 'N(BUG)' : 'S')
   . " | total 0? -> " . (preg_match('/R\$ 0,00/', $r['html']) ? 'S' : 'N') . "\n";

echo "FIM smoke planos\n";
