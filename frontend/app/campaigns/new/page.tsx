"use client";

import React, { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import {
  campaignService,
  CampaignFormData,
} from "@/services/campaign-service";
import { segmentService, Segment } from "@/services/segment-service";
import { templateService, Template } from "@/services/template-service";
import { providerService, Provider } from "@/services/provider-service";
import {
  ArrowLeft,
  ArrowRight,
  CheckCircle2,
  AlertTriangle,
  Mail,
  MessageSquare,
  Users,
  FileText,
  Radio,
  Clock,
  Sparkles,
  ShieldCheck,
  Send,
  Save,
  Check,
  Flame,
  Info,
} from "lucide-react";

export default function NewCampaignWizardPage() {
  const router = useRouter();

  // Step state (1 to 8)
  const [currentStep, setCurrentStep] = useState(1);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Form State
  const [formData, setFormData] = useState<CampaignFormData>({
    name: "",
    description: "",
    channel: "EMAIL",
    segment_id: 0,
    template_id: 0,
    template_version_id: 0,
    provider_id: null,
    scheduled_at: null,
    from_name: "",
    from_email: "",
  });

  const [scheduleType, setScheduleType] = useState<"IMMEDIATE" | "SCHEDULED">("IMMEDIATE");
  const [scheduledDateTime, setScheduledDateTime] = useState("");

  // Dependencies loaded from API
  const [segments, setSegments] = useState<Segment[]>([]);
  const [templates, setTemplates] = useState<Template[]>([]);
  const [providers, setProviders] = useState<Provider[]>([]);
  const [loadingDependencies, setLoadingDependencies] = useState(true);

  // Step 6 & 8 Preview/Validation states
  const [validating, setValidating] = useState(false);
  const [validationErrors, setValidationErrors] = useState<string[]>([]);
  const [previewData, setPreviewData] = useState<any>(null);

  useEffect(() => {
    const loadDependencies = async () => {
      try {
        setLoadingDependencies(true);
        const [segRes, tmplRes, provRes] = await Promise.all([
          segmentService.getSegments({ status: "ACTIVE" }),
          templateService.getTemplates({ status: "ACTIVE" }),
          providerService.list({ status: "ACTIVE" }),
        ]);
        setSegments(segRes.data || []);
        setTemplates(tmplRes.data || []);
        setProviders(provRes.data || []);
      } catch (err: any) {
        setError(err.message || "Erro ao carregar dependências para o assistente.");
      } finally {
        setLoadingDependencies(false);
      }
    };

    loadDependencies();
  }, []);

  // Filter templates & providers by channel
  const filteredTemplates = templates.filter(
    (t) => t.channel === formData.channel && t.status === "ACTIVE"
  );
  const filteredProviders = providers.filter(
    (p) => p.channel === formData.channel && p.status === "ACTIVE"
  );

  // Auto-select template version when template changes
  const handleSelectTemplate = (template: Template) => {
    const versionId = template.current_version_id || (template as any).current_version?.id || (template as any).versions?.[0]?.id || 0;
    setFormData((prev) => ({
      ...prev,
      template_id: template.id,
      template_version_id: versionId,
    }));
  };

  const steps = [
    { id: 1, label: "Identificação", icon: FileText },
    { id: 2, label: "Canal", icon: Mail },
    { id: 3, label: "Segmento", icon: Users },
    { id: 4, label: "Template", icon: Sparkles },
    { id: 5, label: "Provedor", icon: Radio },
    { id: 6, label: "Audiência", icon: ShieldCheck },
    { id: 7, label: "Agendamento", icon: Clock },
    { id: 8, label: "Revisão", icon: CheckCircle2 },
  ];

  const canProceed = () => {
    switch (currentStep) {
      case 1:
        return formData.name.trim().length >= 3;
      case 2:
        return ["EMAIL", "SMS"].includes(formData.channel);
      case 3:
        return formData.segment_id > 0;
      case 4:
        return formData.template_id > 0 && formData.template_version_id > 0;
      case 5:
        return formData.provider_id !== undefined; // optional or selected
      case 6:
        return true;
      case 7:
        if (scheduleType === "SCHEDULED") {
          return scheduledDateTime.trim().length > 0;
        }
        return true;
      case 8:
        return true;
      default:
        return false;
    }
  };

  const nextStep = () => {
    if (!canProceed()) return;
    setError(null);
    setCurrentStep((prev) => Math.min(prev + 1, 8));
  };

  const prevStep = () => {
    setError(null);
    setCurrentStep((prev) => Math.max(prev - 1, 1));
  };

  const handleSaveDraft = async () => {
    try {
      setSaving(true);
      setError(null);

      const payload: CampaignFormData = {
        ...formData,
        scheduled_at: scheduleType === "SCHEDULED" && scheduledDateTime ? new Date(scheduledDateTime).toISOString() : null,
      };

      const created = await campaignService.create(payload);
      router.push(`/campaigns/${created.id}`);
    } catch (err: any) {
      setError(err.message || "Erro ao salvar campanha.");
      setSaving(false);
    }
  };

  const handleSaveAndLaunch = async () => {
    try {
      setSaving(true);
      setError(null);

      const payload: CampaignFormData = {
        ...formData,
        scheduled_at: scheduleType === "SCHEDULED" && scheduledDateTime ? new Date(scheduledDateTime).toISOString() : null,
      };

      // 1. Cria a campanha
      const created = await campaignService.create(payload);

      // 2. Valida
      const val = await campaignService.validate(created.id);
      if (!val.is_valid) {
        setValidationErrors(val.errors);
        setError("A campanha não atende aos critérios prévios de disparo.");
        router.push(`/campaigns/${created.id}`);
        return;
      }

      // 3. Dispara
      await campaignService.launch(created.id, true);
      router.push(`/campaigns/${created.id}`);
    } catch (err: any) {
      setError(err.message || "Erro ao criar e disparar campanha.");
      setSaving(false);
    }
  };

  const selectedSegment = segments.find((s) => s.id === formData.segment_id);
  const selectedTemplate = templates.find((t) => t.id === formData.template_id);
  const selectedProvider = providers.find((p) => p.id === formData.provider_id);

  return (
    <AppLayout>
      <div className="max-w-5xl mx-auto space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <Link
              href="/campaigns"
              className="p-2 hover:bg-gray-800 rounded-lg text-gray-400 hover:text-white transition-colors"
            >
              <ArrowLeft className="w-5 h-5" />
            </Link>
            <div>
              <h1 className="text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                <Flame className="w-6 h-6 text-emerald-500" />
                Criador Guiado de Campanha
              </h1>
              <p className="text-xs text-gray-400 mt-0.5">
                Passo a passo profissional para definição de público, template, canal e agendamento.
              </p>
            </div>
          </div>
        </div>

        {/* Stepper Progress Header */}
        <div className="p-4 bg-gray-900 border border-gray-800 rounded-2xl shadow-sm">
          <div className="grid grid-cols-4 sm:grid-cols-8 gap-2">
            {steps.map((st) => {
              const Icon = st.icon;
              const isPassed = currentStep > st.id;
              const isCurrent = currentStep === st.id;

              return (
                <button
                  key={st.id}
                  onClick={() => st.id < currentStep && setCurrentStep(st.id)}
                  disabled={st.id > currentStep}
                  className={`flex flex-col items-center gap-1.5 p-2 rounded-xl text-center transition-all ${
                    isCurrent
                      ? "bg-emerald-950/80 border border-emerald-700/80 text-emerald-400"
                      : isPassed
                      ? "bg-gray-800/40 text-gray-300 hover:bg-gray-800/80 cursor-pointer"
                      : "opacity-40 text-gray-500 cursor-not-allowed"
                  }`}
                >
                  <div
                    className={`w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold ${
                      isCurrent
                        ? "bg-emerald-600 text-white"
                        : isPassed
                        ? "bg-emerald-950 text-emerald-400 border border-emerald-700"
                        : "bg-gray-800 text-gray-500"
                    }`}
                  >
                    {isPassed ? <Check className="w-3.5 h-3.5" /> : st.id}
                  </div>
                  <span className="text-[11px] font-medium truncate max-w-full">{st.label}</span>
                </button>
              );
            })}
          </div>
        </div>

        {/* Error Alert */}
        {error && (
          <div className="p-4 bg-rose-950/60 border border-rose-800 rounded-xl text-rose-200 text-sm flex items-start gap-2.5">
            <AlertTriangle className="w-5 h-5 flex-shrink-0 text-rose-400 mt-0.5" />
            <div>
              <div className="font-semibold">Não foi possível prosseguir</div>
              <div>{error}</div>
            </div>
          </div>
        )}

        {/* Step Container Card */}
        <div className="p-6 md:p-8 bg-gray-900 border border-gray-800 rounded-2xl shadow-sm min-h-[380px] flex flex-col justify-between">
          {/* STEP 1: IDENTIFICAÇÃO */}
          {currentStep === 1 && (
            <div className="space-y-6">
              <div>
                <h2 className="text-lg font-bold text-white flex items-center gap-2">
                  <FileText className="w-5 h-5 text-emerald-400" />
                  Passo 1: Identificação da Campanha
                </h2>
                <p className="text-xs text-gray-400 mt-1">
                  Defina um nome claro para controle operacional e histórico da equipe de CRM.
                </p>
              </div>

              <div className="space-y-4 max-w-xl">
                <div>
                  <label className="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">
                    Nome da Campanha *
                  </label>
                  <input
                    type="text"
                    placeholder="Ex: Reativação VIP Final de Semana - Bônus 50%"
                    value={formData.name}
                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                    className="w-full px-4 py-2.5 bg-gray-950 border border-gray-800 rounded-lg text-sm text-gray-100 placeholder-gray-600 focus:outline-none focus:border-emerald-500"
                  />
                  <span className="text-[11px] text-gray-500 mt-1 block">Mínimo de 3 caracteres.</span>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">
                    Descrição Interna (Opcional)
                  </label>
                  <textarea
                    rows={3}
                    placeholder="Contexto da ação, objetivo de conversão ou código do cupom associado..."
                    value={formData.description || ""}
                    onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                    className="w-full px-4 py-2.5 bg-gray-950 border border-gray-800 rounded-lg text-sm text-gray-100 placeholder-gray-600 focus:outline-none focus:border-emerald-500"
                  />
                </div>
              </div>
            </div>
          )}

          {/* STEP 2: CANAL */}
          {currentStep === 2 && (
            <div className="space-y-6">
              <div>
                <h2 className="text-lg font-bold text-white flex items-center gap-2">
                  <Mail className="w-5 h-5 text-emerald-400" />
                  Passo 2: Selecione o Canal de Comunicação
                </h2>
                <p className="text-xs text-gray-400 mt-1">
                  O canal determina a disponibilidade dos templates e provedores correspondentes.
                </p>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
                <button
                  type="button"
                  onClick={() =>
                    setFormData({
                      ...formData,
                      channel: "EMAIL",
                      template_id: 0,
                      template_version_id: 0,
                      provider_id: null,
                    })
                  }
                  className={`p-6 rounded-2xl border text-left flex flex-col justify-between transition-all ${
                    formData.channel === "EMAIL"
                      ? "bg-blue-950/40 border-blue-600 ring-2 ring-blue-500/20 shadow-lg"
                      : "bg-gray-950 border-gray-800 hover:border-gray-700"
                  }`}
                >
                  <div className="space-y-2">
                    <div className="w-10 h-10 rounded-xl bg-blue-950 border border-blue-800 flex items-center justify-center text-blue-400">
                      <Mail className="w-5 h-5" />
                    </div>
                    <div className="font-bold text-white text-base">E-mail Marketing</div>
                    <p className="text-xs text-gray-400 leading-relaxed">
                      Mensagens ricas com layout HTML, personalização com variáveis do jogador, preheader e tracking de abertura.
                    </p>
                  </div>
                  <div className="mt-4 flex items-center gap-1.5 text-xs font-semibold text-blue-400">
                    {formData.channel === "EMAIL" ? <CheckCircle2 className="w-4 h-4" /> : null}
                    {formData.channel === "EMAIL" ? "Canal Selecionado" : "Selecionar E-mail"}
                  </div>
                </button>

                <button
                  type="button"
                  onClick={() =>
                    setFormData({
                      ...formData,
                      channel: "SMS",
                      template_id: 0,
                      template_version_id: 0,
                      provider_id: null,
                    })
                  }
                  className={`p-6 rounded-2xl border text-left flex flex-col justify-between transition-all ${
                    formData.channel === "SMS"
                      ? "bg-emerald-950/40 border-emerald-600 ring-2 ring-emerald-500/20 shadow-lg"
                      : "bg-gray-950 border-gray-800 hover:border-gray-700"
                  }`}
                >
                  <div className="space-y-2">
                    <div className="w-10 h-10 rounded-xl bg-emerald-950 border border-emerald-800 flex items-center justify-center text-emerald-400">
                      <MessageSquare className="w-5 h-5" />
                    </div>
                    <div className="font-bold text-white text-base">SMS Direto</div>
                    <p className="text-xs text-gray-400 leading-relaxed">
                      Comunicação imediata de alta taxa de abertura até 160 caracteres, ideal para cupons relâmpago e alertas críticos.
                    </p>
                  </div>
                  <div className="mt-4 flex items-center gap-1.5 text-xs font-semibold text-emerald-400">
                    {formData.channel === "SMS" ? <CheckCircle2 className="w-4 h-4" /> : null}
                    {formData.channel === "SMS" ? "Canal Selecionado" : "Selecionar SMS"}
                  </div>
                </button>
              </div>
            </div>
          )}

          {/* STEP 3: SEGMENTO */}
          {currentStep === 3 && (
            <div className="space-y-6">
              <div>
                <h2 className="text-lg font-bold text-white flex items-center gap-2">
                  <Users className="w-5 h-5 text-emerald-400" />
                  Passo 3: Selecione o Segmento de Jogadores
                </h2>
                <p className="text-xs text-gray-400 mt-1">
                  Apenas segmentos com status ATIVO podem receber disparos de campanha.
                </p>
              </div>

              {segments.length === 0 ? (
                <div className="p-8 text-center bg-gray-950 rounded-xl border border-gray-800 text-gray-400">
                  Nenhum segmento ativo encontrado.{" "}
                  <Link href="/segments/new" className="text-emerald-400 underline">
                    Crie um segmento primeiro.
                  </Link>
                </div>
              ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  {segments.map((seg) => (
                    <button
                      key={seg.id}
                      type="button"
                      onClick={() => setFormData({ ...formData, segment_id: seg.id })}
                      className={`p-4 rounded-xl border text-left transition-all ${
                        formData.segment_id === seg.id
                          ? "bg-emerald-950/40 border-emerald-600 ring-2 ring-emerald-500/20"
                          : "bg-gray-950 border-gray-800 hover:border-gray-700"
                      }`}
                    >
                      <div className="flex items-center justify-between mb-2">
                        <span className="font-semibold text-white">{seg.name}</span>
                        <span className="text-xs text-gray-400 bg-gray-900 px-2 py-0.5 rounded border border-gray-800">
                          {seg.cached_count?.toLocaleString("pt-BR") || 0} jogadores
                        </span>
                      </div>
                      <p className="text-xs text-gray-400 line-clamp-2">{seg.description || "Segmento sem descrição."}</p>
                      <div className="mt-3 flex items-center gap-1.5 text-xs font-semibold text-emerald-400">
                        {formData.segment_id === seg.id && <Check className="w-3.5 h-3.5" />}
                        {formData.segment_id === seg.id ? "Segmento Selecionado" : "Selecionar"}
                      </div>
                    </button>
                  ))}
                </div>
              )}
            </div>
          )}

          {/* STEP 4: TEMPLATE */}
          {currentStep === 4 && (
            <div className="space-y-6">
              <div>
                <h2 className="text-lg font-bold text-white flex items-center gap-2">
                  <Sparkles className="w-5 h-5 text-emerald-400" />
                  Passo 4: Selecione o Template Publicado ({formData.channel})
                </h2>
                <p className="text-xs text-gray-400 mt-1">
                  Templates com versão publicada (PUBLISHED) compatíveis com o canal selecionado.
                </p>
              </div>

              {filteredTemplates.length === 0 ? (
                <div className="p-8 text-center bg-gray-950 rounded-xl border border-gray-800 text-gray-400">
                  Nenhum template ativo encontrado para o canal {formData.channel}.{" "}
                  <Link href="/templates/new" className="text-emerald-400 underline">
                    Crie e publique um template.
                  </Link>
                </div>
              ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  {filteredTemplates.map((tmpl) => (
                    <button
                      key={tmpl.id}
                      type="button"
                      onClick={() => handleSelectTemplate(tmpl)}
                      className={`p-4 rounded-xl border text-left transition-all ${
                        formData.template_id === tmpl.id
                          ? "bg-emerald-950/40 border-emerald-600 ring-2 ring-emerald-500/20"
                          : "bg-gray-950 border-gray-800 hover:border-gray-700"
                      }`}
                    >
                      <div className="flex items-center justify-between mb-1.5">
                        <span className="font-semibold text-white">{tmpl.name}</span>
                        <span className="text-[11px] text-gray-400 bg-gray-900 px-2 py-0.5 rounded border border-gray-800 uppercase">
                          {tmpl.category}
                        </span>
                      </div>
                      <p className="text-xs text-gray-400 line-clamp-1">{tmpl.slug}</p>
                      <div className="mt-3 flex items-center justify-between text-xs text-gray-500">
                        <span>Versão ativa: v{tmpl.current_version?.version || 1}</span>
                        <span className="text-emerald-400 font-semibold flex items-center gap-1">
                          {formData.template_id === tmpl.id && <Check className="w-3.5 h-3.5" />}
                          {formData.template_id === tmpl.id ? "Selecionado" : "Escolher"}
                        </span>
                      </div>
                    </button>
                  ))}
                </div>
              )}
            </div>
          )}

          {/* STEP 5: PROVEDOR */}
          {currentStep === 5 && (
            <div className="space-y-6">
              <div>
                <h2 className="text-lg font-bold text-white flex items-center gap-2">
                  <Radio className="w-5 h-5 text-emerald-400" />
                  Passo 5: Selecione o Provedor de Disparo ({formData.channel})
                </h2>
                <p className="text-xs text-gray-400 mt-1">
                  Deixe vazio para utilizar o provedor padrão configurado para a plataforma.
                </p>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <button
                  type="button"
                  onClick={() => setFormData({ ...formData, provider_id: null })}
                  className={`p-4 rounded-xl border text-left transition-all ${
                    formData.provider_id === null
                      ? "bg-emerald-950/40 border-emerald-600 ring-2 ring-emerald-500/20"
                      : "bg-gray-950 border-gray-800 hover:border-gray-700"
                  }`}
                >
                  <div className="font-semibold text-white">Provedor Padrão da Plataforma</div>
                  <p className="text-xs text-gray-400 mt-1">
                    Utiliza o roteamento automático do CRM e políticas de failover configuradas.
                  </p>
                  <div className="mt-3 text-xs font-semibold text-emerald-400">
                    {formData.provider_id === null ? "✓ Padrão Selecionado" : "Usar Padrão"}
                  </div>
                </button>

                {filteredProviders.map((prov) => (
                  <button
                    key={prov.id}
                    type="button"
                    onClick={() => setFormData({ ...formData, provider_id: prov.id })}
                    className={`p-4 rounded-xl border text-left transition-all ${
                      formData.provider_id === prov.id
                        ? "bg-emerald-950/40 border-emerald-600 ring-2 ring-emerald-500/20"
                        : "bg-gray-950 border-gray-800 hover:border-gray-700"
                    }`}
                  >
                    <div className="flex items-center justify-between">
                      <span className="font-semibold text-white">{prov.name}</span>
                      <span className="text-[11px] text-gray-400 uppercase font-mono">{prov.driver}</span>
                    </div>
                    <p className="text-xs text-gray-400 mt-1">
                      Limite: {prov.rate_limit_per_minute} msgs/min • Prioridade #{prov.priority}
                    </p>
                    <div className="mt-3 text-xs font-semibold text-emerald-400">
                      {formData.provider_id === prov.id ? "✓ Provedor Selecionado" : "Selecionar"}
                    </div>
                  </button>
                ))}
              </div>
            </div>
          )}

          {/* STEP 6: AUDIÊNCIA */}
          {currentStep === 6 && (
            <div className="space-y-6">
              <div>
                <h2 className="text-lg font-bold text-white flex items-center gap-2">
                  <ShieldCheck className="w-5 h-5 text-emerald-400" />
                  Passo 6: Análise de Audiência e Conformidade LGPD
                </h2>
                <p className="text-xs text-gray-400 mt-1">
                  O BET CRM aplica de forma estrita filtros mandatórios de consentimento ativo e contato válido antes do disparo.
                </p>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div className="p-4 bg-gray-950 border border-gray-800 rounded-xl space-y-1">
                  <span className="text-xs text-gray-500 font-semibold uppercase">Público do Segmento</span>
                  <div className="text-2xl font-bold text-white">
                    {selectedSegment?.cached_count?.toLocaleString("pt-BR") || 0}
                  </div>
                  <span className="text-[11px] text-gray-400">Total de jogadores no segmento</span>
                </div>

                <div className="p-4 bg-gray-950 border border-gray-800 rounded-xl space-y-1">
                  <span className="text-xs text-gray-500 font-semibold uppercase">Governança LGPD</span>
                  <div className="text-2xl font-bold text-emerald-400">ATIVO</div>
                  <span className="text-[11px] text-emerald-500/80">Consentimento por canal obrigatório</span>
                </div>

                <div className="p-4 bg-gray-950 border border-gray-800 rounded-xl space-y-1">
                  <span className="text-xs text-gray-500 font-semibold uppercase">Deduplicação</span>
                  <div className="text-2xl font-bold text-blue-400">ESTRITA</div>
                  <span className="text-[11px] text-blue-500/80">1 mensagem por jogador garantida</span>
                </div>
              </div>

              <div className="p-4 bg-blue-950/20 border border-blue-900/40 rounded-xl text-xs text-blue-200/90 leading-relaxed flex items-start gap-2.5">
                <Info className="w-5 h-5 text-blue-400 flex-shrink-0 mt-0.5" />
                <div>
                  No momento do lançamento, o motor de snapshot congelará a lista de destinatários em{" "}
                  <code className="text-white font-mono bg-blue-950 px-1 py-0.5 rounded">campaign_recipients</code>.
                  Jogadores sem consentimento explícito no canal {formData.channel} ou com status BLOQUEADO serão
                  automaticamente marcados como <strong>SKIPPED</strong> com registro auditável.
                </div>
              </div>
            </div>
          )}

          {/* STEP 7: AGENDAMENTO & REMETENTE */}
          {currentStep === 7 && (
            <div className="space-y-6">
              <div>
                <h2 className="text-lg font-bold text-white flex items-center gap-2">
                  <Clock className="w-5 h-5 text-emerald-400" />
                  Passo 7: Configuração de Envio e Remetente
                </h2>
                <p className="text-xs text-gray-400 mt-1">
                  Defina se o disparo ocorrerá imediatamente ou agendado para data/hora futura.
                </p>
              </div>

              <div className="space-y-5 max-w-xl">
                <div className="grid grid-cols-2 gap-3">
                  <button
                    type="button"
                    onClick={() => setScheduleType("IMMEDIATE")}
                    className={`p-4 rounded-xl border text-left transition-all ${
                      scheduleType === "IMMEDIATE"
                        ? "bg-emerald-950/40 border-emerald-600 ring-2 ring-emerald-500/20 text-white"
                        : "bg-gray-950 border-gray-800 text-gray-400 hover:text-white"
                    }`}
                  >
                    <div className="font-semibold text-sm">Disparo Imediato</div>
                    <div className="text-xs text-gray-400 mt-1">Inicia o processamento nas filas assim que confirmado.</div>
                  </button>

                  <button
                    type="button"
                    onClick={() => setScheduleType("SCHEDULED")}
                    className={`p-4 rounded-xl border text-left transition-all ${
                      scheduleType === "SCHEDULED"
                        ? "bg-purple-950/40 border-purple-600 ring-2 ring-purple-500/20 text-white"
                        : "bg-gray-950 border-gray-800 text-gray-400 hover:text-white"
                    }`}
                  >
                    <div className="font-semibold text-sm">Agendamento Futuro</div>
                    <div className="text-xs text-gray-400 mt-1">O Scheduler do Laravel iniciará o disparo no momento exato.</div>
                  </button>
                </div>

                {scheduleType === "SCHEDULED" && (
                  <div>
                    <label className="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">
                      Data e Hora do Disparo *
                    </label>
                    <input
                      type="datetime-local"
                      value={scheduledDateTime}
                      onChange={(e) => setScheduledDateTime(e.target.value)}
                      className="w-full px-4 py-2.5 bg-gray-950 border border-gray-800 rounded-lg text-sm text-gray-100 focus:outline-none focus:border-purple-500"
                    />
                  </div>
                )}

                {formData.channel === "EMAIL" && (
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-gray-800">
                    <div>
                      <label className="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">
                        Nome do Remetente (Opcional)
                      </label>
                      <input
                        type="text"
                        placeholder="Ex: Bet Brasil Promoções"
                        value={formData.from_name || ""}
                        onChange={(e) => setFormData({ ...formData, from_name: e.target.value })}
                        className="w-full px-3 py-2 bg-gray-950 border border-gray-800 rounded-lg text-sm text-gray-100 placeholder-gray-600 focus:outline-none focus:border-emerald-500"
                      />
                    </div>

                    <div>
                      <label className="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">
                        E-mail do Remetente (Opcional)
                      </label>
                      <input
                        type="email"
                        placeholder="promocoes@betbrasil.com"
                        value={formData.from_email || ""}
                        onChange={(e) => setFormData({ ...formData, from_email: e.target.value })}
                        className="w-full px-3 py-2 bg-gray-950 border border-gray-800 rounded-lg text-sm text-gray-100 placeholder-gray-600 focus:outline-none focus:border-emerald-500"
                      />
                    </div>
                  </div>
                )}
              </div>
            </div>
          )}

          {/* STEP 8: REVISÃO & FINALIZAÇÃO */}
          {currentStep === 8 && (
            <div className="space-y-6">
              <div>
                <h2 className="text-lg font-bold text-white flex items-center gap-2">
                  <CheckCircle2 className="w-5 h-5 text-emerald-400" />
                  Passo 8: Validação Prévia & Revisão da Campanha
                </h2>
                <p className="text-xs text-gray-400 mt-1">
                  Confirme todas as informações antes de salvar o rascunho ou iniciar o disparo.
                </p>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="p-5 bg-gray-950 border border-gray-800 rounded-xl space-y-3">
                  <div className="text-xs font-bold uppercase text-gray-400 tracking-wider">Resumo da Configuração</div>
                  <div className="space-y-2 text-xs">
                    <div className="flex justify-between border-b border-gray-800/60 pb-1.5">
                      <span className="text-gray-400">Nome:</span>
                      <span className="text-white font-semibold">{formData.name}</span>
                    </div>
                    <div className="flex justify-between border-b border-gray-800/60 pb-1.5">
                      <span className="text-gray-400">Canal:</span>
                      <span className="text-emerald-400 font-semibold">{formData.channel}</span>
                    </div>
                    <div className="flex justify-between border-b border-gray-800/60 pb-1.5">
                      <span className="text-gray-400">Segmento:</span>
                      <span className="text-white font-medium">{selectedSegment?.name}</span>
                    </div>
                    <div className="flex justify-between border-b border-gray-800/60 pb-1.5">
                      <span className="text-gray-400">Template:</span>
                      <span className="text-white font-medium">{selectedTemplate?.name}</span>
                    </div>
                    <div className="flex justify-between border-b border-gray-800/60 pb-1.5">
                      <span className="text-gray-400">Provedor:</span>
                      <span className="text-white font-medium">{selectedProvider?.name || "Padrão da Plataforma"}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-gray-400">Momento de Disparo:</span>
                      <span className="text-purple-400 font-medium">
                        {scheduleType === "SCHEDULED" ? new Date(scheduledDateTime).toLocaleString("pt-BR") : "Imediato"}
                      </span>
                    </div>
                  </div>
                </div>

                <div className="p-5 bg-gray-950 border border-gray-800 rounded-xl space-y-3">
                  <div className="text-xs font-bold uppercase text-gray-400 tracking-wider">Auditoria e Segurança</div>
                  <ul className="space-y-2 text-xs text-gray-300">
                    <li className="flex items-center gap-2">
                      <Check className="w-4 h-4 text-emerald-400 flex-shrink-0" />
                      Idempotência estrita ativada por chave única de envio.
                    </li>
                    <li className="flex items-center gap-2">
                      <Check className="w-4 h-4 text-emerald-400 flex-shrink-0" />
                      Snapshot imutável de destinatários na fila.
                    </li>
                    <li className="flex items-center gap-2">
                      <Check className="w-4 h-4 text-emerald-400 flex-shrink-0" />
                      Bloqueio automático de jogadores sem consentimento LGPD.
                    </li>
                    <li className="flex items-center gap-2">
                      <Check className="w-4 h-4 text-emerald-400 flex-shrink-0" />
                      Isolamento multi-tenant garantido por PlatformScope.
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          )}

          {/* Stepper Footer Controls */}
          <div className="flex items-center justify-between pt-6 border-t border-gray-800 mt-8">
            <button
              type="button"
              onClick={prevStep}
              disabled={currentStep === 1 || saving}
              className="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-lg text-sm font-medium transition-colors disabled:opacity-30 disabled:cursor-not-allowed flex items-center gap-2"
            >
              <ArrowLeft className="w-4 h-4" />
              Anterior
            </button>

            <div className="flex items-center gap-3">
              {currentStep < 8 ? (
                <button
                  type="button"
                  onClick={nextStep}
                  disabled={!canProceed()}
                  className="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-semibold transition-colors disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-2"
                >
                  Próximo
                  <ArrowRight className="w-4 h-4" />
                </button>
              ) : (
                <>
                  <button
                    type="button"
                    onClick={handleSaveDraft}
                    disabled={saving}
                    className="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-200 rounded-lg text-sm font-medium transition-colors flex items-center gap-2 disabled:opacity-50"
                  >
                    <Save className="w-4 h-4" />
                    Salvar Rascunho
                  </button>

                  <button
                    type="button"
                    onClick={handleSaveAndLaunch}
                    disabled={saving}
                    className="px-6 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-bold transition-colors flex items-center gap-2 shadow-lg shadow-emerald-900/50 disabled:opacity-50"
                  >
                    <Send className="w-4 h-4" />
                    {saving ? "Processando..." : scheduleType === "SCHEDULED" ? "Agendar Campanha" : "Salvar e Disparar"}
                  </button>
                </>
              )}
            </div>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
