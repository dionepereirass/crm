# BET CRM — CONFORMIDADE LGPD (LEI Nº 13.709/2018)

## 1. Atendimento aos Direitos do Titular (Art. 18)

O BET CRM implementa um fluxo formal e auditado para o atendimento de todos os direitos dos titulares de dados (jogadores e apostadores):

```text
Solicitação Aberta (OPEN)
         ↓
Atribuição a Operador DPO/Compliance (ASSIGN)
         ↓
Em Análise / Processamento (IN_PROGRESS)
         ↓
Resolução (COMPLETED / REJECTED / CANCELLED)
```

### Tipos de Solicitação Suportados (`DataSubjectRequestType`):

1. **`ACCESS` (Acesso & Confirmação)**: Relatório completo com dados cadastrais, consentimentos, histórico e interações.
2. **`CORRECTION` (Correção)**: Atualização de informações incompletas, incorretas ou desatualizadas.
3. **`PORTABILITY` (Portabilidade)**: Exportação estruturada em JSON interoperável (`DataExportService`), omitindo dados de inteligência proprietária e credenciais.
4. **`DELETION` (Eliminação)**: Exclusão de dados tratados sob consentimento, sujeita às salvaguardas legais de retenção regulatória.
5. **`REVOCATION` (Revogação de Consentimento)**: Revogação imediata de permissões de marketing e comunicação.
6. **`INFORMATION` (Informação)**: Transparência quanto aos operadores e provedores terceiros com os quais os dados são transacionados.
7. **`ANONYMIZATION` (Anonimização)**: Desvinculação irreversível de identidade do apostador mantendo integridade financeira.

---

## 2. Controle de SLA Legal de 15 Dias

Conforme determina a LGPD, o prazo legal para resposta conclusiva é de 15 dias corridos a partir da data de protocolo.

- **Cálculo Automático**: `due_at = requested_at + 15 dias`.
- **Alerta Preventivo de SLA (`isNearSla()`)**: Sinaliza solicitações pendentes a menos de 48 horas do vencimento.
- **Detecção de Expiração (`isExpired()`)**: Marca visualmente solicitações que ultrapassaram o prazo legal sem conclusão.

---

## 3. Política de Eliminação vs. Anonimização (Art. 16, I)

No setor de apostas de quota fixa e iGaming, a legislação federal brasileira (Lei nº 14.790/2023, Portarias SPA/MF e normas de Prevenção à Lavagem de Dinheiro — PLD/FT) e o **Art. 16, I da LGPD** determinam a guarda obrigatória do histórico contábil, de apostas e depósitos por prazo mínimo legal (tipicamente 5 anos).

### Regra do `DeletionPolicyService`:
- Se o jogador **NÃO possui movimentação financeira** (depósitos, saques ou apostas registradas):
  - A solicitação de `DELETION` executa a exclusão de fato dos registros cadastrais e de contato.
- Se o jogador **POSSUI movimentação financeira**:
  - A solicitação é automaticamente convertida em **ANONIMIZAÇÃO IRREVERSÍVEL**.
  - Os dados cadastrais são ofuscados permanentemente, mas o registro contábil e a trilha de auditoria permanecem íntegros para fins regulatórios e fiscais.

---

## 4. Algoritmo de Anonimização Irreversível (`PlayerAnonymizationService`)

A operação de anonimização executa as seguintes transformações permanentes:

```php
$player->update([
    'name' => "ANONYMIZED_USER_{$player->id}",
    'email' => 'anon_' . hash('sha256', $player->id . $player->platform_id) . '@anonymized.local',
    'phone' => null,
    'cpf' => null,
    'birth_date' => null,
    'country' => null,
    'state' => null,
    'city' => null,
    'status' => 'INACTIVE',
    'metadata' => [
        'anonymized' => true,
        'anonymized_at' => now()->toIso8601String(),
    ],
]);
```

Além disso:
1. **Revoga todos os consentimentos** associados ao jogador e grava o evento imutável em `consent_history`.
2. **Remove tags de segmentação** ativas do jogador.
3. **Preserva foreign keys e saldos históricos**, impedindo quebras de integridade referencial em relatórios contábeis da plataforma.
4. **Registra entrada de auditoria** com ação `ANONYMIZE` no `AuditLog`.
