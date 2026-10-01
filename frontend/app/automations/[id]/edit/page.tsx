"use client";

import React, { useEffect, useState } from "react";
import { useParams, useRouter } from "next/navigation";
import Link from "next/link";
import { AppLayout } from "@/components/layout/app-layout";
import {
  automationService,
  Automation,
  AutomationNode,
  AutomationEdge,
  ValidationResult,
} from "@/services/automation-service";
import {
  Zap,
  ArrowLeft,
  Save,
  Play,
  Plus,
  Trash2,
  GitBranch,
  Send,
  Clock,
  Layers,
  CheckCircle2,
  AlertTriangle,
  XCircle,
  HelpCircle,
  Settings,
  Mail,
  MessageSquare,
  Tag,
  ChevronRight,
  ShieldCheck,
  RefreshCw,
} from "lucide-react";

export default function AutomationEditPage() {
  const params = useParams();
  const router = useRouter();
  const id = Number(params?.id);

  const [automation, setAutomation] = useState<Automation | null>(null);
  const [nodes, setNodes] = useState<AutomationNode[]>([]);
  const [edges, setEdges] = useState<AutomationEdge[]>([]);
  const [selectedNodeKey, setSelectedNodeKey] = useState<string | null>(null);

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [validating, setValidating] = useState(false);
  const [validationResult, setValidationResult] = useState<ValidationResult | null>(null);

  const [errorMsg, setErrorMsg] = useState<string | null>(null);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);

  // New Edge Connector Modal / Drawer state
  const [connectingSourceKey, setConnectingSourceKey] = useState<string | null>(null);
  const [connectingTargetKey, setConnectingTargetKey] = useState<string>("");
  const [connectingConditionKey, setConnectingConditionKey] = useState<string>("true");

  const loadGraph = async () => {
    if (!id) return;
    try {
      setLoading(true);
      setErrorMsg(null);
      const res = await automationService.getGraph(id);
      setAutomation(res.automation);

      if (res.nodes && res.nodes.length > 0) {
        setNodes(res.nodes);
        setEdges(res.edges || []);
      } else {
        // Inicializa com nó de Gatilho padrão correspondente à automação
        const triggerKey = "trigger_1";
        const initialTriggerNode: AutomationNode = {
          node_key: triggerKey,
          node_type: "TRIGGER",
          name: `Gatilho: ${res.automation?.trigger_type || "Novo Cadastro"}`,
          configuration: { trigger_type: res.automation?.trigger_type || "PLAYER_CREATED" },
          position_x: 200,
          position_y: 50,
        };
        setNodes([initialTriggerNode]);
        setEdges([]);
        setSelectedNodeKey(triggerKey);
      }
    } catch (err: any) {
      setErrorMsg(err.message || "Erro ao carregar grafo da automação.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadGraph();
  }, [id]);

  const selectedNode = nodes.find((n) => n.node_key === selectedNodeKey) || null;

  // Add node of given type
  const handleAddNode = (type: "TRIGGER" | "CONDITION" | "ACTION" | "WAIT") => {
    const nextIndex = nodes.length + 1;
    const newKey = `${type.toLowerCase()}_${Date.now().toString().slice(-4)}`;

    let defaultConfig: Record<string, any> = {};
    let defaultName = `Novo Nó ${type}`;

    if (type === "ACTION") {
      defaultConfig = { action_type: "SEND_EMAIL", template_id: null, provider_id: null };
      defaultName = "Enviar E-mail";
    } else if (type === "CONDITION") {
      defaultConfig = { condition_type: "RULES", rules: [{ field: "total_deposits_count", operator: "gt", value: 0 }] };
      defaultName = "Verificar Depósitos";
    } else if (type === "WAIT") {
      defaultConfig = { wait_type: "DURATION", duration_value: 1, duration_unit: "DAYS" };
      defaultName = "Aguardar 1 Dia";
    } else if (type === "TRIGGER") {
      defaultConfig = { trigger_type: automation?.trigger_type || "PLAYER_CREATED" };
      defaultName = "Gatilho Inicial";
    }

    const newNode: AutomationNode = {
      node_key: newKey,
      node_type: type,
      name: defaultName,
      configuration: defaultConfig,
      position_x: 200,
      position_y: (nodes.length + 1) * 120,
    };

    setNodes((prev) => [...prev, newNode]);
    setSelectedNodeKey(newKey);
    setSuccessMsg(`Nó "${defaultName}" adicionado ao grafo.`);
  };

  // Delete node and associated edges
  const handleDeleteNode = (key: string) => {
    if (nodes.length <= 1) {
      setErrorMsg("O grafo deve conter pelo menos o nó de gatilho inicial.");
      return;
    }
    setNodes((prev) => prev.filter((n) => n.node_key !== key));
    setEdges((prev) => prev.filter((e) => e.source_node_key !== key && e.target_node_key !== key));
    if (selectedNodeKey === key) {
      setSelectedNodeKey(null);
    }
    setSuccessMsg(`Nó removido.`);
  };

  // Update selected node config or name
  const handleUpdateSelectedNode = (field: string, value: any) => {
    if (!selectedNodeKey) return;
    setNodes((prev) =>
      prev.map((n) => {
        if (n.node_key === selectedNodeKey) {
          if (field.startsWith("config.")) {
            const configKey = field.replace("config.", "");
            return {
              ...n,
              configuration: {
                ...n.configuration,
                [configKey]: value,
              },
            };
          }
          return { ...n, [field]: value };
        }
        return n;
      })
    );
  };

  // Add Edge connection
  const handleCreateEdge = (e: React.FormEvent) => {
    e.preventDefault();
    if (!connectingSourceKey || !connectingTargetKey) return;

    if (connectingSourceKey === connectingTargetKey) {
      setErrorMsg("Um nó não pode apontar para si mesmo.");
      return;
    }

    const sourceNode = nodes.find((n) => n.node_key === connectingSourceKey);
    const isCondition = sourceNode?.node_type === "CONDITION";

    // Check duplicate edge
    const exists = edges.some(
      (edge) =>
        edge.source_node_key === connectingSourceKey &&
        edge.target_node_key === connectingTargetKey &&
        (!isCondition || edge.condition_key === connectingConditionKey)
    );

    if (exists) {
      setErrorMsg("Esta conexão já existe.");
      return;
    }

    const newEdge: AutomationEdge = {
      source_node_key: connectingSourceKey,
      target_node_key: connectingTargetKey,
      condition_key: isCondition ? connectingConditionKey : null,
    };

    setEdges((prev) => [...prev, newEdge]);
    setConnectingSourceKey(null);
    setConnectingTargetKey("");
    setSuccessMsg("Conexão estabelecida com sucesso.");
  };

  const handleDeleteEdge = (index: number) => {
    setEdges((prev) => prev.filter((_, i) => i !== index));
  };

  // Save graph
  const handleSaveGraph = async (showSuccess = true) => {
    try {
      setSaving(true);
      setErrorMsg(null);
      await automationService.saveGraph(id, nodes, edges);
      if (showSuccess) setSuccessMsg("Grafo salvo com sucesso!");
      return true;
    } catch (err: any) {
      setErrorMsg(err.message || "Falha ao salvar o grafo.");
      return false;
    } finally {
      setSaving(false);
    }
  };

  // Validate graph
  const handleValidateGraph = async () => {
    const saved = await handleSaveGraph(false);
    if (!saved) return;

    try {
      setValidating(true);
      const res = await automationService.validate(id);
      setValidationResult(res);
      if (res.valid) {
        setSuccessMsg("Grafo validado com sucesso! Nenhuma inconsistência encontrada.");
      } else {
        setErrorMsg(`Validação identificou ${res.errors.length} erro(s). Corrija antes de ativar.`);
      }
    } catch (err: any) {
      setErrorMsg(err.message || "Erro na validação do grafo.");
    } finally {
      setValidating(false);
    }
  };

  // Save & Activate
  const handleSaveAndActivate = async () => {
    const saved = await handleSaveGraph(false);
    if (!saved) return;

    try {
      setSaving(true);
      await automationService.activate(id);
      setSuccessMsg("Automação salva e ativada com sucesso!");
      router.push(`/automations/${id}`);
    } catch (err: any) {
      setErrorMsg(err.message || "Falha ao ativar a automação.");
    } finally {
      setSaving(false);
    }
  };

  // Seed standard onboarding template
  const handleApplyWelcomeTemplate = () => {
    const templateNodes: AutomationNode[] = [
      {
        node_key: "trigger_1",
        node_type: "TRIGGER",
        name: "Cadastro de Jogador",
        configuration: { trigger_type: "PLAYER_CREATED" },
        position_x: 200,
        position_y: 50,
      },
      {
        node_key: "action_email_welcome",
        node_type: "ACTION",
        name: "E-mail de Boas-vindas",
        configuration: { action_type: "SEND_EMAIL", template_id: 1, provider_id: 1 },
        position_x: 200,
        position_y: 180,
      },
      {
        node_key: "wait_1_day",
        node_type: "WAIT",
        name: "Aguardar 24 Horas",
        configuration: { wait_type: "DURATION", duration_value: 24, duration_unit: "HOURS" },
        position_x: 200,
        position_y: 310,
      },
      {
        node_key: "cond_deposit_made",
        node_type: "CONDITION",
        name: "Realizou Primeiro Depósito?",
        configuration: {
          condition_type: "RULES",
          rules: [{ field: "total_deposits_count", operator: "gt", value: 0 }],
        },
        position_x: 200,
        position_y: 440,
      },
      {
        node_key: "action_tag_vip",
        node_type: "ACTION",
        name: "Adicionar Tag 'Ativo'",
        configuration: { action_type: "ADD_TAG", tag_name: "Ativo" },
        position_x: 100,
        position_y: 580,
      },
      {
        node_key: "action_sms_reminder",
        node_type: "ACTION",
        name: "SMS Lembrete Bônus",
        configuration: { action_type: "SEND_SMS", template_id: 2, provider_id: 2 },
        position_x: 350,
        position_y: 580,
      },
    ];

    const templateEdges: AutomationEdge[] = [
      { source_node_key: "trigger_1", target_node_key: "action_email_welcome" },
      { source_node_key: "action_email_welcome", target_node_key: "wait_1_day" },
      { source_node_key: "wait_1_day", target_node_key: "cond_deposit_made" },
      { source_node_key: "cond_deposit_made", target_node_key: "action_tag_vip", condition_key: "true" },
      { source_node_key: "cond_deposit_made", target_node_key: "action_sms_reminder", condition_key: "false" },
    ];

    setNodes(templateNodes);
    setEdges(templateEdges);
    setSelectedNodeKey("trigger_1");
    setSuccessMsg("Template de Boas-Vindas aplicado no canvas.");
  };

  const getNodeIcon = (type: string) => {
    switch (type) {
      case "TRIGGER":
        return <Zap className="w-4 h-4 text-amber-400" />;
      case "CONDITION":
        return <GitBranch className="w-4 h-4 text-purple-400" />;
      case "ACTION":
        return <Send className="w-4 h-4 text-emerald-400" />;
      case "WAIT":
        return <Clock className="w-4 h-4 text-blue-400" />;
      default:
        return <Layers className="w-4 h-4 text-slate-400" />;
    }
  };

  if (loading) {
    return (
      <AppLayout title="Editor de Grafo">
        <div className="p-16 text-center text-slate-400">
          <RefreshCw className="w-8 h-8 animate-spin mx-auto mb-3 text-emerald-500" />
          Carregando editor de automação...
        </div>
      </AppLayout>
    );
  }

  return (
    <AppLayout
      title={`Editor de Grafo • ${automation?.name || ""}`}
      badge="FASE 10 • ENGINE VISUAL"
      actions={
        <div className="flex items-center gap-2">
          <Link
            href={`/automations/${id}`}
            className="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-sm border border-slate-700 flex items-center gap-1.5 transition"
          >
            <ArrowLeft className="w-4 h-4" />
            Voltar
          </Link>

          <button
            onClick={handleValidateGraph}
            disabled={validating || saving}
            className="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-sm border border-slate-700 flex items-center gap-1.5 transition"
          >
            <ShieldCheck className={`w-4 h-4 ${validating ? "animate-spin" : "text-emerald-400"}`} />
            Validar
          </button>

          <button
            onClick={() => handleSaveGraph(true)}
            disabled={saving}
            className="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm font-semibold flex items-center gap-1.5 transition"
          >
            <Save className={`w-4 h-4 ${saving ? "animate-spin" : ""}`} />
            Salvar Grafo
          </button>

          <button
            onClick={handleSaveAndActivate}
            disabled={saving}
            className="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-semibold flex items-center gap-1.5 transition shadow-sm shadow-emerald-900/40"
          >
            <Play className="w-4 h-4" />
            Salvar e Ativar
          </button>
        </div>
      }
    >
      <div className="space-y-4">
        {/* Alerts */}
        {errorMsg && (
          <div className="p-4 bg-rose-950/60 border border-rose-800 rounded-lg text-rose-200 text-sm flex items-center gap-2">
            <AlertTriangle className="w-5 h-5 flex-shrink-0 text-rose-400" />
            <span>{errorMsg}</span>
          </div>
        )}

        {successMsg && (
          <div className="p-4 bg-emerald-950/60 border border-emerald-800 rounded-lg text-emerald-200 text-sm flex items-center gap-2">
            <CheckCircle2 className="w-5 h-5 flex-shrink-0 text-emerald-400" />
            <span>{successMsg}</span>
          </div>
        )}

        {/* Validation Errors Panel */}
        {validationResult && !validationResult.valid && (
          <div className="p-4 bg-rose-950/70 border border-rose-800 rounded-xl space-y-2">
            <div className="flex items-center gap-2 text-rose-300 font-semibold text-sm">
              <XCircle className="w-4 h-4" />
              Erros de Validação no Grafo:
            </div>
            <ul className="text-xs text-rose-200 space-y-1 list-disc list-inside">
              {validationResult.errors.map((e, idx) => (
                <li key={idx}>
                  <strong className="font-mono text-rose-100">[{e.node}]</strong> {e.message}
                </li>
              ))}
            </ul>
          </div>
        )}

        {/* Palette Bar */}
        <div className="p-3 bg-slate-900 border border-slate-800 rounded-xl flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-2">
            <span className="text-xs uppercase tracking-wider font-semibold text-slate-400 mr-2">
              Adicionar Nó:
            </span>

            <button
              onClick={() => handleAddNode("ACTION")}
              className="px-3 py-1.5 bg-emerald-950/60 hover:bg-emerald-900/80 text-emerald-300 border border-emerald-800 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition"
            >
              <Send className="w-3.5 h-3.5" />
              + Ação (E-mail/SMS/Tag)
            </button>

            <button
              onClick={() => handleAddNode("CONDITION")}
              className="px-3 py-1.5 bg-purple-950/60 hover:bg-purple-900/80 text-purple-300 border border-purple-800 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition"
            >
              <GitBranch className="w-3.5 h-3.5" />
              + Condição (IF / ELSE)
            </button>

            <button
              onClick={() => handleAddNode("WAIT")}
              className="px-3 py-1.5 bg-blue-950/60 hover:bg-blue-900/80 text-blue-300 border border-blue-800 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition"
            >
              <Clock className="w-3.5 h-3.5" />
              + Espera (Delay)
            </button>
          </div>

          <div className="flex items-center gap-2">
            <button
              onClick={handleApplyWelcomeTemplate}
              className="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 rounded-lg text-xs font-medium transition"
            >
              Aplicar Template Boas-Vindas
            </button>
          </div>
        </div>

        {/* Main Workspace: Visual Canvas + Sidebar Inspector */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 min-h-[600px]">
          {/* Canvas Area (Cols 1-8) */}
          <div className="lg:col-span-8 bg-slate-950 border border-slate-800 rounded-xl p-6 relative overflow-y-auto max-h-[750px] space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-slate-900 text-xs text-slate-400">
              <span className="font-semibold text-slate-300 uppercase tracking-wider">
                Fluxo da Automação ({nodes.length} Nós • {edges.length} Conexões)
              </span>
              <span className="text-[11px] text-slate-500">Clique em um nó para inspecionar e configurar</span>
            </div>

            {/* Nodes Stack */}
            <div className="space-y-4">
              {nodes.map((node, index) => {
                const isSelected = selectedNodeKey === node.node_key;
                const outgoingEdges = edges.filter((e) => e.source_node_key === node.node_key);

                return (
                  <div
                    key={node.node_key}
                    onClick={() => setSelectedNodeKey(node.node_key)}
                    className={`p-4 rounded-xl border transition cursor-pointer relative ${
                      isSelected
                        ? "bg-slate-900 border-emerald-500/80 shadow-lg shadow-emerald-950/30 ring-1 ring-emerald-500/40"
                        : "bg-slate-900/70 border-slate-800 hover:border-slate-700"
                    }`}
                  >
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-3">
                        <div className="p-2 rounded-lg bg-slate-950 border border-slate-800">
                          {getNodeIcon(node.node_type)}
                        </div>
                        <div>
                          <div className="flex items-center gap-2">
                            <h4 className="text-sm font-semibold text-white">{node.name}</h4>
                            <span className="text-[10px] font-mono px-2 py-0.5 rounded bg-slate-950 text-slate-400 border border-slate-800">
                              {node.node_type}
                            </span>
                          </div>
                          <span className="text-xs text-slate-500 font-mono">key: {node.node_key}</span>
                        </div>
                      </div>

                      <div className="flex items-center gap-2">
                        <button
                          type="button"
                          onClick={(e) => {
                            e.stopPropagation();
                            setConnectingSourceKey(node.node_key);
                          }}
                          className="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs rounded border border-slate-700 flex items-center gap-1 transition"
                        >
                          <ChevronRight className="w-3 h-3 text-emerald-400" />
                          Conectar Próximo
                        </button>

                        {node.node_type !== "TRIGGER" && (
                          <button
                            type="button"
                            onClick={(e) => {
                              e.stopPropagation();
                              handleDeleteNode(node.node_key);
                            }}
                            className="p-1.5 hover:bg-rose-950/50 text-slate-500 hover:text-rose-400 rounded transition"
                          >
                            <Trash2 className="w-3.5 h-3.5" />
                          </button>
                        )}
                      </div>
                    </div>

                    {/* Node Config Preview */}
                    <div className="mt-3 pt-2 border-t border-slate-800/60 text-xs text-slate-400">
                      {node.node_type === "ACTION" && (
                        <span>
                          Ação: <strong className="text-slate-200">{node.configuration?.action_type || "N/A"}</strong>
                          {node.configuration?.action_type === "SEND_EMAIL" && ` • Template #${node.configuration?.template_id || "?"}`}
                          {node.configuration?.action_type === "SEND_SMS" && ` • Template #${node.configuration?.template_id || "?"}`}
                          {node.configuration?.tag_name && ` • Tag: "${node.configuration?.tag_name}"`}
                        </span>
                      )}
                      {node.node_type === "CONDITION" && (
                        <span>
                          Critério: <strong className="text-slate-200">{node.configuration?.condition_type || "Regras Customizadas"}</strong>
                        </span>
                      )}
                      {node.node_type === "WAIT" && (
                        <span>
                          Duração: <strong className="text-slate-200">{node.configuration?.duration_value} {node.configuration?.duration_unit}</strong>
                        </span>
                      )}
                      {node.node_type === "TRIGGER" && (
                        <span>
                          Evento: <strong className="text-slate-200">{node.configuration?.trigger_type}</strong>
                        </span>
                      )}
                    </div>

                    {/* Outgoing Connectors List */}
                    {outgoingEdges.length > 0 && (
                      <div className="mt-2.5 flex flex-wrap gap-2">
                        {outgoingEdges.map((edge, eIdx) => {
                          const targetNode = nodes.find((n) => n.node_key === edge.target_node_key);
                          const edgeGlobalIndex = edges.findIndex((e) => e === edge);
                          return (
                            <span
                              key={eIdx}
                              className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] bg-slate-950 border border-slate-800 text-slate-300"
                            >
                              <span className="text-emerald-400 font-mono">→</span>
                              {edge.condition_key && (
                                <strong
                                  className={`font-mono ${
                                    edge.condition_key === "true"
                                      ? "text-emerald-400"
                                      : "text-amber-400"
                                  }`}
                                >
                                  [{edge.condition_key}]
                                </strong>
                              )}
                              <span>{targetNode?.name || edge.target_node_key}</span>
                              <button
                                type="button"
                                onClick={(e) => {
                                  e.stopPropagation();
                                  handleDeleteEdge(edgeGlobalIndex);
                                }}
                                className="text-slate-500 hover:text-rose-400 ml-1"
                              >
                                ×
                              </button>
                            </span>
                          );
                        })}
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          </div>

          {/* Inspector Drawer (Cols 9-12) */}
          <div className="lg:col-span-4 bg-slate-900 border border-slate-800 rounded-xl p-5 space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-slate-800">
              <div className="flex items-center gap-2">
                <Settings className="w-4 h-4 text-emerald-400" />
                <h3 className="font-semibold text-white text-sm">Configuração do Nó</h3>
              </div>
              {selectedNode && (
                <span className="text-[10px] font-mono px-2 py-0.5 rounded bg-slate-950 text-slate-400 border border-slate-800">
                  {selectedNode.node_type}
                </span>
              )}
            </div>

            {selectedNode ? (
              <div className="space-y-4 text-xs">
                <div>
                  <label className="block text-slate-400 font-semibold uppercase tracking-wider mb-1">
                    Nome do Nó
                  </label>
                  <input
                    type="text"
                    value={selectedNode.name}
                    onChange={(e) => handleUpdateSelectedNode("name", e.target.value)}
                    className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-emerald-500"
                  />
                </div>

                {/* Configuration based on NodeType */}
                {selectedNode.node_type === "ACTION" && (
                  <div className="space-y-3 pt-2 border-t border-slate-800">
                    <div>
                      <label className="block text-slate-400 font-semibold uppercase tracking-wider mb-1">
                        Tipo de Ação
                      </label>
                      <select
                        value={selectedNode.configuration?.action_type || "SEND_EMAIL"}
                        onChange={(e) => handleUpdateSelectedNode("config.action_type", e.target.value)}
                        className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-white focus:outline-none focus:border-emerald-500"
                      >
                        <option value="SEND_EMAIL">Enviar E-mail (LGPD Consent Obrigatório)</option>
                        <option value="SEND_SMS">Enviar SMS (LGPD Consent Obrigatório)</option>
                        <option value="ADD_TAG">Adicionar Tag ao Jogador</option>
                        <option value="REMOVE_TAG">Remover Tag do Jogador</option>
                        <option value="ENTER_SEGMENT">Adicionar a Segmento Estático</option>
                        <option value="EXIT_SEGMENT">Remover de Segmento Estático</option>
                      </select>
                    </div>

                    {(selectedNode.configuration?.action_type === "SEND_EMAIL" ||
                      selectedNode.configuration?.action_type === "SEND_SMS") && (
                      <>
                        <div>
                          <label className="block text-slate-400 font-semibold uppercase tracking-wider mb-1">
                            ID do Template Publicado *
                          </label>
                          <input
                            type="number"
                            placeholder="Ex: 1"
                            value={selectedNode.configuration?.template_id || ""}
                            onChange={(e) =>
                              handleUpdateSelectedNode("config.template_id", Number(e.target.value))
                            }
                            className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-white focus:outline-none focus:border-emerald-500"
                          />
                          <p className="text-[11px] text-slate-500 mt-1">
                            O template deve estar em status PUBLISHED e compatível com o canal.
                          </p>
                        </div>

                        <div>
                          <label className="block text-slate-400 font-semibold uppercase tracking-wider mb-1">
                            ID do Provedor (Opcional - Fallback Automático)
                          </label>
                          <input
                            type="number"
                            placeholder="Deixe vazio para provedor padrão"
                            value={selectedNode.configuration?.provider_id || ""}
                            onChange={(e) =>
                              handleUpdateSelectedNode(
                                "config.provider_id",
                                e.target.value ? Number(e.target.value) : null
                              )
                            }
                            className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-white focus:outline-none focus:border-emerald-500"
                          />
                        </div>
                      </>
                    )}

                    {(selectedNode.configuration?.action_type === "ADD_TAG" ||
                      selectedNode.configuration?.action_type === "REMOVE_TAG") && (
                      <div>
                        <label className="block text-slate-400 font-semibold uppercase tracking-wider mb-1">
                          Nome da Tag *
                        </label>
                        <input
                          type="text"
                          placeholder="Ex: Depositante, VIP Bronze, Churn Risk"
                          value={selectedNode.configuration?.tag_name || ""}
                          onChange={(e) => handleUpdateSelectedNode("config.tag_name", e.target.value)}
                          className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-white focus:outline-none focus:border-emerald-500"
                        />
                      </div>
                    )}
                  </div>
                )}

                {selectedNode.node_type === "WAIT" && (
                  <div className="space-y-3 pt-2 border-t border-slate-800">
                    <div>
                      <label className="block text-slate-400 font-semibold uppercase tracking-wider mb-1">
                        Tipo de Espera
                      </label>
                      <select
                        value={selectedNode.configuration?.wait_type || "DURATION"}
                        onChange={(e) => handleUpdateSelectedNode("config.wait_type", e.target.value)}
                        className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-white focus:outline-none focus:border-emerald-500"
                      >
                        <option value="DURATION">Duração Relativa (Minutos, Horas, Dias)</option>
                        <option value="UNTIL_DATE">Data / Horário Específico</option>
                      </select>
                    </div>

                    {selectedNode.configuration?.wait_type !== "UNTIL_DATE" ? (
                      <div className="grid grid-cols-2 gap-2">
                        <div>
                          <label className="block text-slate-400 font-semibold mb-1">Quantidade</label>
                          <input
                            type="number"
                            min={1}
                            value={selectedNode.configuration?.duration_value || 1}
                            onChange={(e) =>
                              handleUpdateSelectedNode("config.duration_value", Number(e.target.value))
                            }
                            className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-white focus:outline-none focus:border-emerald-500"
                          />
                        </div>
                        <div>
                          <label className="block text-slate-400 font-semibold mb-1">Unidade</label>
                          <select
                            value={selectedNode.configuration?.duration_unit || "HOURS"}
                            onChange={(e) => handleUpdateSelectedNode("config.duration_unit", e.target.value)}
                            className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-white focus:outline-none focus:border-emerald-500"
                          >
                            <option value="MINUTES">Minutos</option>
                            <option value="HOURS">Horas</option>
                            <option value="DAYS">Dias</option>
                          </select>
                        </div>
                      </div>
                    ) : (
                      <div>
                        <label className="block text-slate-400 font-semibold mb-1">Aguardar Até (ISO)</label>
                        <input
                          type="datetime-local"
                          value={selectedNode.configuration?.scheduled_at || ""}
                          onChange={(e) => handleUpdateSelectedNode("config.scheduled_at", e.target.value)}
                          className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-white focus:outline-none focus:border-emerald-500"
                        />
                      </div>
                    )}
                  </div>
                )}

                {selectedNode.node_type === "CONDITION" && (
                  <div className="space-y-3 pt-2 border-t border-slate-800">
                    <div>
                      <label className="block text-slate-400 font-semibold uppercase tracking-wider mb-1">
                        Modo de Avaliação
                      </label>
                      <select
                        value={selectedNode.configuration?.condition_type || "RULES"}
                        onChange={(e) => handleUpdateSelectedNode("config.condition_type", e.target.value)}
                        className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-white focus:outline-none focus:border-emerald-500"
                      >
                        <option value="RULES">Regras Atômicas do Jogador</option>
                        <option value="SEGMENT">Pertencimento a Segmento Dinâmico</option>
                        <option value="CAMPAIGN_INTERACTION">Interação de Mensagens (Abertura / Clique)</option>
                      </select>
                    </div>

                    {selectedNode.configuration?.condition_type === "SEGMENT" ? (
                      <div>
                        <label className="block text-slate-400 font-semibold mb-1">ID do Segmento *</label>
                        <input
                          type="number"
                          placeholder="Ex: 5"
                          value={selectedNode.configuration?.segment_id || ""}
                          onChange={(e) =>
                            handleUpdateSelectedNode("config.segment_id", Number(e.target.value))
                          }
                          className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-white focus:outline-none focus:border-emerald-500"
                        />
                      </div>
                    ) : (
                      <div className="space-y-2">
                        <label className="block text-slate-400 font-semibold">Configuração da Regra</label>
                        <div className="p-2.5 bg-slate-950 border border-slate-800 rounded-lg space-y-2">
                          <div>
                            <span className="text-[11px] text-slate-400">Campo:</span>
                            <select
                              value={selectedNode.configuration?.rules?.[0]?.field || "total_deposits_count"}
                              onChange={(e) => {
                                const curr = selectedNode.configuration?.rules || [{}];
                                curr[0] = { ...curr[0], field: e.target.value };
                                handleUpdateSelectedNode("config.rules", [...curr]);
                              }}
                              className="w-full mt-1 px-2 py-1.5 bg-slate-900 border border-slate-800 rounded text-slate-200 text-xs"
                            >
                              <option value="total_deposits_count">Total de Depósitos (Qtd)</option>
                              <option value="total_deposits_amount">Volume Total Depositado (R$)</option>
                              <option value="total_bets_count">Total de Apostas (Qtd)</option>
                              <option value="current_balance">Saldo Atual (R$)</option>
                              <option value="kyc_status">Status KYC</option>
                            </select>
                          </div>

                          <div className="grid grid-cols-2 gap-2">
                            <div>
                              <span className="text-[11px] text-slate-400">Operador:</span>
                              <select
                                value={selectedNode.configuration?.rules?.[0]?.operator || "gt"}
                                onChange={(e) => {
                                  const curr = selectedNode.configuration?.rules || [{}];
                                  curr[0] = { ...curr[0], operator: e.target.value };
                                  handleUpdateSelectedNode("config.rules", [...curr]);
                                }}
                                className="w-full mt-1 px-2 py-1.5 bg-slate-900 border border-slate-800 rounded text-slate-200 text-xs"
                              >
                                <option value="gt">&gt; Maior que</option>
                                <option value="gte">&gt;= Maior ou igual</option>
                                <option value="eq">= Igual</option>
                                <option value="lt">&lt; Menor que</option>
                                <option value="lte">&lt;= Menor ou igual</option>
                              </select>
                            </div>
                            <div>
                              <span className="text-[11px] text-slate-400">Valor:</span>
                              <input
                                type="text"
                                value={selectedNode.configuration?.rules?.[0]?.value ?? 0}
                                onChange={(e) => {
                                  const curr = selectedNode.configuration?.rules || [{}];
                                  curr[0] = { ...curr[0], value: e.target.value };
                                  handleUpdateSelectedNode("config.rules", [...curr]);
                                }}
                                className="w-full mt-1 px-2 py-1.5 bg-slate-900 border border-slate-800 rounded text-slate-200 text-xs"
                              />
                            </div>
                          </div>
                        </div>
                      </div>
                    )}
                  </div>
                )}

                {selectedNode.node_type === "TRIGGER" && (
                  <div className="space-y-3 pt-2 border-t border-slate-800">
                    <p className="text-slate-400">
                      O gatilho de início é definido globalmente para a automação.
                    </p>
                    <div className="p-3 bg-slate-950 border border-slate-800 rounded-lg text-amber-400 font-mono">
                      Tipo: {selectedNode.configuration?.trigger_type || automation?.trigger_type}
                    </div>
                  </div>
                )}
              </div>
            ) : (
              <div className="py-12 text-center text-slate-500 text-xs">
                Selecione um nó no painel ao lado para editar suas propriedades e parâmetros.
              </div>
            )}
          </div>
        </div>

        {/* Modal / Connector Dialog */}
        {connectingSourceKey && (
          <div className="fixed inset-0 bg-black/75 z-50 flex items-center justify-center p-4">
            <div className="bg-slate-900 border border-slate-800 rounded-xl max-w-md w-full p-6 space-y-4 shadow-2xl">
              <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 className="text-sm font-semibold text-white flex items-center gap-2">
                  <ChevronRight className="w-4 h-4 text-emerald-400" />
                  Conectar Nós do Grafo
                </h3>
                <button
                  type="button"
                  onClick={() => setConnectingSourceKey(null)}
                  className="text-slate-500 hover:text-white"
                >
                  ✕
                </button>
              </div>

              <form onSubmit={handleCreateEdge} className="space-y-4 text-xs">
                <div>
                  <label className="block text-slate-400 mb-1">Nó de Origem:</label>
                  <input
                    type="text"
                    disabled
                    value={nodes.find((n) => n.node_key === connectingSourceKey)?.name || connectingSourceKey}
                    className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded text-slate-300 font-medium"
                  />
                </div>

                {/* If source is condition, select true/false */}
                {nodes.find((n) => n.node_key === connectingSourceKey)?.node_type === "CONDITION" && (
                  <div>
                    <label className="block text-slate-400 mb-1">Ramificação da Condição:</label>
                    <select
                      value={connectingConditionKey}
                      onChange={(e) => setConnectingConditionKey(e.target.value)}
                      className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded text-white focus:outline-none focus:border-emerald-500"
                    >
                      <option value="true">Verdadeiro (TRUE) - Atende aos critérios</option>
                      <option value="false">Falso (FALSE) - Não atende aos critérios</option>
                    </select>
                  </div>
                )}

                <div>
                  <label className="block text-slate-400 mb-1">Conectar ao Nó de Destino *:</label>
                  <select
                    required
                    value={connectingTargetKey}
                    onChange={(e) => setConnectingTargetKey(e.target.value)}
                    className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded text-white focus:outline-none focus:border-emerald-500"
                  >
                    <option value="">Selecione o nó de destino...</option>
                    {nodes
                      .filter((n) => n.node_key !== connectingSourceKey && n.node_type !== "TRIGGER")
                      .map((n) => (
                        <option key={n.node_key} value={n.node_key}>
                          [{n.node_type}] {n.name}
                        </option>
                      ))}
                  </select>
                </div>

                <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-800">
                  <button
                    type="button"
                    onClick={() => setConnectingSourceKey(null)}
                    className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded font-medium"
                  >
                    Cancelar
                  </button>
                  <button
                    type="submit"
                    className="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded font-semibold flex items-center gap-1.5"
                  >
                    Salvar Conexão
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
