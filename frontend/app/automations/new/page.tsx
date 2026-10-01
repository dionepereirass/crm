"use client";

import React, { useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import { automationService } from "@/services/automation-service";
import {
  Zap,
  ArrowLeft,
  CheckCircle2,
  AlertTriangle,
  Settings,
  Shield,
  Clock,
  Sparkles,
  ChevronRight,
} from "lucide-react";

export default function NewAutomationPage() {
  const router = useRouter();

  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [triggerType, setTriggerType] = useState("PLAYER_CREATED");
  const [reentryPolicy, setReentryPolicy] = useState<"ALLOW_REENTRY" | "BLOCK_REENTRY" | "REENTRY_AFTER">("BLOCK_REENTRY");
  const [reentryAfterDays, setReentryAfterDays] = useState(30);
  const [cooldownDays, setCooldownDays] = useState(0);
  const [maxStepsPerRun, setMaxStepsPerRun] = useState(50);

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const triggers = [
    {
      type: "PLAYER_CREATED",
      name: "Novo Cadastro de Jogador",
      desc: "Disparado imediatamente após o registro do player na plataforma.",
      category: "Onboarding",
    },
    {
      type: "PLAYER_VERIFIED",
      name: "Conta de Jogador Verificada",
      desc: "Disparado quando a identidade ou e-mail/telefone do jogador é validada.",
      category: "Onboarding",
    },
    {
      type: "DEPOSIT_INITIATED",
      name: "Depósito Iniciado",
      desc: "Disparado quando um PIX/boleto/cartão é gerado mas ainda não foi pago.",
      category: "Financeiro",
    },
    {
      type: "DEPOSIT_SUCCESS",
      name: "Primeiro ou Novo Depósito Aprovado",
      desc: "Disparado no momento exato em que o saldo é creditado.",
      category: "Financeiro",
    },
    {
      type: "DEPOSIT_FAILED",
      name: "Falha de Depósito",
      desc: "Disparado quando o pagamento expira ou é recusado pelo gateway.",
      category: "Financeiro",
    },
    {
      type: "WITHDRAWAL_INITIATED",
      name: "Saque Solicitado",
      desc: "Disparado quando o jogador abre um pedido de saque de saldo.",
      category: "Financeiro",
    },
    {
      type: "WITHDRAWAL_SUCCESS",
      name: "Saque Concluído",
      desc: "Disparado após a liquidação do saque na conta do jogador.",
      category: "Financeiro",
    },
    {
      type: "BET_PLACED",
      name: "Aposta Realizada",
      desc: "Disparado a cada bilhete de aposta esportiva ou rodada de cassino.",
      category: "Gaming",
    },
    {
      type: "BET_WON",
      name: "Aposta Vencedora (Big Win)",
      desc: "Disparado quando o jogador obtém ganho em suas apostas.",
      category: "Gaming",
    },
    {
      type: "BET_LOST",
      name: "Aposta Perdida (Cashback / Churn risk)",
      desc: "Disparado quando uma aposta é liquidada como derrota.",
      category: "Gaming",
    },
    {
      type: "LOGIN_FAILED",
      name: "Falha de Autenticação",
      desc: "Disparado após tentativas inválidas de acesso.",
      category: "Segurança",
    },
    {
      type: "PASSWORD_RESET",
      name: "Redefinição de Senha",
      desc: "Disparado na solicitação de recuperação de credencial.",
      category: "Segurança",
    },
    {
      type: "PLAYER_INACTIVITY",
      name: "Inatividade do Jogador",
      desc: "Disparado quando o player fica N dias sem apostar ou logar.",
      category: "Retenção",
    },
  ];

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) {
      setError("Por favor, informe o nome da automação.");
      return;
    }

    try {
      setLoading(true);
      setError(null);

      const res = await automationService.create({
        name: name.trim(),
        description: description.trim() || undefined,
        trigger_type: triggerType,
        settings: {
          reentry_policy: reentryPolicy,
          reentry_after_days: reentryPolicy === "REENTRY_AFTER" ? Number(reentryAfterDays) : undefined,
          cooldown_days: Number(cooldownDays),
          max_steps_per_run: Number(maxStepsPerRun),
        },
      });

      // Redireciona para o canvas de edição do grafo
      router.push(`/automations/${res.data.id}/edit`);
    } catch (err: any) {
      setError(err.message || "Erro ao criar automação.");
      setLoading(false);
    }
  };

  return (
    <AppLayout
      title="Nova Automação"
      badge="FASE 10 • WIZARD DE CRIAÇÃO"
      actions={
        <Link
          href="/automations"
          className="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-sm font-medium border border-slate-700 flex items-center gap-2 transition"
        >
          <ArrowLeft className="w-4 h-4" />
          Voltar para Lista
        </Link>
      }
    >
      <div className="max-w-4xl mx-auto space-y-6">
        {error && (
          <div className="p-4 bg-rose-950/60 border border-rose-800 rounded-lg text-rose-200 text-sm flex items-center gap-2">
            <AlertTriangle className="w-5 h-5 flex-shrink-0 text-rose-400" />
            <span>{error}</span>
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Card 1: Identificação Básica */}
          <div className="p-6 bg-slate-900 border border-slate-800 rounded-xl space-y-4">
            <div className="flex items-center gap-2 pb-3 border-b border-slate-800">
              <Sparkles className="w-5 h-5 text-emerald-400" />
              <h2 className="text-base font-semibold text-white">Identificação da Jornada</h2>
            </div>

            <div className="space-y-4">
              <div>
                <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                  Nome da Automação *
                </label>
                <input
                  type="text"
                  required
                  placeholder="Ex: Boas-vindas com Primeiro Depósito (3 dias)"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  className="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                  Descrição do Objetivo (Opcional)
                </label>
                <textarea
                  rows={2}
                  placeholder="Descreva a finalidade desta jornada, público alvo ou regras de conversão esperadas..."
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  className="w-full px-4 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500"
                />
              </div>
            </div>
          </div>

          {/* Card 2: Gatilho de Entrada */}
          <div className="p-6 bg-slate-900 border border-slate-800 rounded-xl space-y-4">
            <div className="flex items-center gap-2 pb-3 border-b border-slate-800">
              <Zap className="w-5 h-5 text-amber-400" />
              <h2 className="text-base font-semibold text-white">Gatilho de Início da Jornada</h2>
            </div>
            <p className="text-xs text-slate-400">
              Selecione o evento do sistema ou webhook que irá instanciar uma nova execução da jornada para o jogador.
            </p>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
              {triggers.map((t) => {
                const isSelected = triggerType === t.type;
                return (
                  <div
                    key={t.type}
                    onClick={() => setTriggerType(t.type)}
                    className={`p-3.5 rounded-lg border cursor-pointer transition flex flex-col justify-between ${
                      isSelected
                        ? "bg-emerald-950/40 border-emerald-500/80 ring-1 ring-emerald-500/50"
                        : "bg-slate-950 border-slate-800 hover:border-slate-700"
                    }`}
                  >
                    <div>
                      <div className="flex items-center justify-between mb-1">
                        <span className="text-xs font-mono px-2 py-0.5 rounded bg-slate-900 text-slate-400 border border-slate-800">
                          {t.category}
                        </span>
                        {isSelected && <CheckCircle2 className="w-4 h-4 text-emerald-400" />}
                      </div>
                      <h3 className="text-sm font-semibold text-white mt-1">{t.name}</h3>
                      <p className="text-xs text-slate-400 mt-1 line-clamp-2">{t.desc}</p>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>

          {/* Card 3: Políticas de Reentrada e Segurança */}
          <div className="p-6 bg-slate-900 border border-slate-800 rounded-xl space-y-4">
            <div className="flex items-center gap-2 pb-3 border-b border-slate-800">
              <Shield className="w-5 h-5 text-blue-400" />
              <h2 className="text-base font-semibold text-white">Políticas de Reentrada & Cooldown</h2>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">
                  Política de Reentrada
                </label>
                <div className="space-y-2">
                  <label className="flex items-start gap-2.5 p-3 rounded-lg border border-slate-800 bg-slate-950 cursor-pointer">
                    <input
                      type="radio"
                      name="reentry_policy"
                      value="BLOCK_REENTRY"
                      checked={reentryPolicy === "BLOCK_REENTRY"}
                      onChange={() => setReentryPolicy("BLOCK_REENTRY")}
                      className="mt-0.5 text-emerald-500 focus:ring-emerald-500"
                    />
                    <div className="text-xs">
                      <span className="font-semibold text-slate-200 block">Bloquear Reentrada (Uma única vez)</span>
                      <span className="text-slate-400">O jogador só pode percorrer esta jornada uma vez na vida.</span>
                    </div>
                  </label>

                  <label className="flex items-start gap-2.5 p-3 rounded-lg border border-slate-800 bg-slate-950 cursor-pointer">
                    <input
                      type="radio"
                      name="reentry_policy"
                      value="ALLOW_REENTRY"
                      checked={reentryPolicy === "ALLOW_REENTRY"}
                      onChange={() => setReentryPolicy("ALLOW_REENTRY")}
                      className="mt-0.5 text-emerald-500 focus:ring-emerald-500"
                    />
                    <div className="text-xs">
                      <span className="font-semibold text-slate-200 block">Permitir Reentrada Sempre</span>
                      <span className="text-slate-400">Cada novo evento dispara uma nova instância da jornada.</span>
                    </div>
                  </label>

                  <label className="flex items-start gap-2.5 p-3 rounded-lg border border-slate-800 bg-slate-950 cursor-pointer">
                    <input
                      type="radio"
                      name="reentry_policy"
                      value="REENTRY_AFTER"
                      checked={reentryPolicy === "REENTRY_AFTER"}
                      onChange={() => setReentryPolicy("REENTRY_AFTER")}
                      className="mt-0.5 text-emerald-500 focus:ring-emerald-500"
                    />
                    <div className="text-xs">
                      <span className="font-semibold text-slate-200 block">Reentrada após Intervalo</span>
                      <span className="text-slate-400">Permite reingressar apenas após N dias da conclusão anterior.</span>
                    </div>
                  </label>
                </div>

                {reentryPolicy === "REENTRY_AFTER" && (
                  <div className="mt-3">
                    <label className="block text-xs font-semibold text-slate-300 mb-1">
                      Intervalo para Reentrada (Dias)
                    </label>
                    <input
                      type="number"
                      min={1}
                      max={365}
                      value={reentryAfterDays}
                      onChange={(e) => setReentryAfterDays(Number(e.target.value))}
                      className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-emerald-500"
                    />
                  </div>
                )}
              </div>

              <div className="space-y-4">
                <div>
                  <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                    Cooldown Global (Dias)
                  </label>
                  <input
                    type="number"
                    min={0}
                    max={365}
                    value={cooldownDays}
                    onChange={(e) => setCooldownDays(Number(e.target.value))}
                    className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-emerald-500"
                  />
                  <p className="text-[11px] text-slate-500 mt-1">
                    Tempo mínimo entre execuções consecutivas para o mesmo jogador (0 = desativado).
                  </p>
                </div>

                <div>
                  <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                    Limite Máximo de Passos por Execução
                  </label>
                  <input
                    type="number"
                    min={5}
                    max={200}
                    value={maxStepsPerRun}
                    onChange={(e) => setMaxStepsPerRun(Number(e.target.value))}
                    className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-emerald-500"
                  />
                  <p className="text-[11px] text-slate-500 mt-1">
                    Proteção contra loops ou encadeamentos recursivos anômalos (padrão: 50 passos).
                  </p>
                </div>
              </div>
            </div>
          </div>

          {/* Submit */}
          <div className="flex items-center justify-end gap-3 pt-2">
            <Link
              href="/automations"
              className="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-sm font-semibold transition"
            >
              Cancelar
            </Link>
            <button
              type="submit"
              disabled={loading}
              className="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-semibold flex items-center gap-2 transition shadow-md shadow-emerald-900/50 disabled:opacity-50"
            >
              {loading ? "Criando Automação..." : "Criar e Abrir Editor de Grafo"}
              <ChevronRight className="w-4 h-4" />
            </button>
          </div>
        </form>
      </div>
    </AppLayout>
  );
}
