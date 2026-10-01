"use client";

import React, { useState, useEffect } from "react";
import {
  SegmentGroupItem,
  SegmentConditionItem,
  SegmentFieldDefinition,
  SegmentOperatorDefinition,
  segmentService,
  SegmentPreviewResult,
} from "@/services/segment-service";
import {
  Plus,
  Trash2,
  FolderPlus,
  Play,
  Users,
  Clock,
  Sparkles,
  Layers,
  ChevronRight,
  Filter,
  CheckCircle2,
  AlertCircle,
} from "lucide-react";

interface SegmentBuilderProps {
  initialRules?: SegmentGroupItem;
  onChange?: (rules: SegmentGroupItem) => void;
  onPreviewSuccess?: (result: SegmentPreviewResult) => void;
  readOnly?: boolean;
}

export function SegmentBuilder({
  initialRules,
  onChange,
  onPreviewSuccess,
  readOnly = false,
}: SegmentBuilderProps) {
  const [rules, setRules] = useState<SegmentGroupItem>(
    initialRules || {
      operator: "AND",
      children: [
        {
          type: "condition",
          field: "player.status",
          operator: "equals",
          value: "ACTIVE",
        },
      ],
    }
  );

  const [fieldCategories, setFieldCategories] = useState<Record<string, Record<string, SegmentFieldDefinition>>>({});
  const [allFields, setAllFields] = useState<Record<string, SegmentFieldDefinition>>({});
  const [operatorCategories, setOperatorCategories] = useState<Record<string, Record<string, SegmentOperatorDefinition>>>({});
  const [allOperators, setAllOperators] = useState<Record<string, SegmentOperatorDefinition>>({});
  const [loadingCatalogs, setLoadingCatalogs] = useState(true);

  // Preview state
  const [previewLoading, setPreviewLoading] = useState(false);
  const [previewResult, setPreviewResult] = useState<SegmentPreviewResult | null>(null);
  const [previewError, setPreviewError] = useState<string | null>(null);

  useEffect(() => {
    let isMounted = true;
    async function loadCatalogs() {
      try {
        const [fieldsRes, operatorsRes] = await Promise.all([
          segmentService.getFields(),
          segmentService.getOperators(),
        ]);
        if (isMounted) {
          setFieldCategories(fieldsRes.data);
          setAllFields(fieldsRes.all);
          setOperatorCategories(operatorsRes.data);
          setAllOperators(operatorsRes.all);
          setLoadingCatalogs(false);
        }
      } catch (err) {
        console.error("Erro ao carregar catálogos de segmentação:", err);
        if (isMounted) setLoadingCatalogs(false);
      }
    }
    loadCatalogs();
    return () => {
      isMounted = false;
    };
  }, []);

  const updateRules = (newRules: SegmentGroupItem) => {
    setRules(newRules);
    if (onChange) {
      onChange(newRules);
    }
  };

  const handlePreview = async () => {
    setPreviewLoading(true);
    setPreviewError(null);
    try {
      const res = await segmentService.previewRules(rules, 10);
      setPreviewResult(res);
      if (onPreviewSuccess) {
        onPreviewSuccess(res);
      }
    } catch (err: any) {
      setPreviewError(err.message || "Erro ao calcular prévia da audiência.");
    } finally {
      setPreviewLoading(false);
    }
  };

  // Helper methods to mutate tree
  const updateGroupOperator = (path: number[], op: "AND" | "OR") => {
    const clone = JSON.parse(JSON.stringify(rules)) as SegmentGroupItem;
    let curr: any = clone;
    for (const idx of path) {
      curr = curr.children[idx];
    }
    curr.operator = op;
    updateRules(clone);
  };

  const addConditionToGroup = (path: number[]) => {
    const clone = JSON.parse(JSON.stringify(rules)) as SegmentGroupItem;
    let curr: any = clone;
    for (const idx of path) {
      curr = curr.children[idx];
    }
    curr.children.push({
      type: "condition",
      field: "player.status",
      operator: "equals",
      value: "ACTIVE",
    });
    updateRules(clone);
  };

  const addSubGroupToGroup = (path: number[]) => {
    const clone = JSON.parse(JSON.stringify(rules)) as SegmentGroupItem;
    let curr: any = clone;
    for (const idx of path) {
      curr = curr.children[idx];
    }
    curr.children.push({
      type: "group",
      operator: "OR",
      children: [
        {
          type: "condition",
          field: "player.state",
          operator: "equals",
          value: "SP",
        },
      ],
    });
    updateRules(clone);
  };

  const removeNodeFromGroup = (path: number[], childIndex: number) => {
    const clone = JSON.parse(JSON.stringify(rules)) as SegmentGroupItem;
    let curr: any = clone;
    for (const idx of path) {
      curr = curr.children[idx];
    }
    curr.children.splice(childIndex, 1);
    updateRules(clone);
  };

  const updateCondition = (
    path: number[],
    childIndex: number,
    updates: Partial<SegmentConditionItem>
  ) => {
    const clone = JSON.parse(JSON.stringify(rules)) as SegmentGroupItem;
    let curr: any = clone;
    for (const idx of path) {
      curr = curr.children[idx];
    }
    curr.children[childIndex] = {
      ...curr.children[childIndex],
      ...updates,
    };
    updateRules(clone);
  };

  // Recursive Group Renderer
  const renderGroup = (
    group: SegmentGroupItem,
    path: number[],
    isRoot: boolean = false
  ) => {
    const isAnd = group.operator === "AND";

    return (
      <div
        className={`relative rounded-xl border p-4 transition-all ${
          isRoot
            ? "border-emerald-500/20 bg-[#0d1322]"
            : "border-slate-800/80 bg-[#090d18] ml-4 md:ml-6 mt-3 shadow-inner"
        }`}
      >
        {/* Group Header */}
        <div className="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-800/60">
          <div className="flex items-center gap-2">
            <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">
              {isRoot ? "Correspondência Raiz:" : "Sub-Grupo Lógico:"}
            </span>

            {readOnly ? (
              <span
                className={`px-3 py-1 rounded-md text-xs font-bold uppercase ${
                  isAnd
                    ? "bg-emerald-500/10 text-emerald-400 border border-emerald-500/30"
                    : "bg-blue-500/10 text-blue-400 border border-blue-500/30"
                }`}
              >
                {group.operator} (
                {isAnd ? "Todas as condições" : "Qualquer uma das condições"})
              </span>
            ) : (
              <div className="inline-flex rounded-lg border border-slate-800 p-0.5 bg-slate-900/80">
                <button
                  type="button"
                  onClick={() => updateGroupOperator(path, "AND")}
                  className={`px-3 py-1 text-xs font-bold rounded-md transition-colors ${
                    isAnd
                      ? "bg-emerald-600 text-white shadow-sm"
                      : "text-slate-400 hover:text-slate-200"
                  }`}
                >
                  AND (Todas)
                </button>
                <button
                  type="button"
                  onClick={() => updateGroupOperator(path, "OR")}
                  className={`px-3 py-1 text-xs font-bold rounded-md transition-colors ${
                    !isAnd
                      ? "bg-blue-600 text-white shadow-sm"
                      : "text-slate-400 hover:text-slate-200"
                  }`}
                >
                  OR (Qualquer)
                </button>
              </div>
            )}
          </div>

          {!readOnly && (
            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => addConditionToGroup(path)}
                className="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-lg bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20 border border-emerald-500/30 transition-colors"
              >
                <Plus className="w-3.5 h-3.5" />
                Adicionar Regra
              </button>
              <button
                type="button"
                onClick={() => addSubGroupToGroup(path)}
                className="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-lg bg-slate-800 text-slate-300 hover:bg-slate-700 border border-slate-700 transition-colors"
              >
                <FolderPlus className="w-3.5 h-3.5" />
                Novo Sub-Grupo
              </button>
            </div>
          )}
        </div>

        {/* Children (Conditions & Sub-groups) */}
        <div className="mt-3 space-y-3">
          {group.children.length === 0 ? (
            <div className="text-center py-6 text-xs text-slate-500 italic">
              Nenhuma condição neste grupo. Clique em "Adicionar Regra" acima.
            </div>
          ) : (
            group.children.map((child, idx) => {
              if (child.type === "group" || (child as SegmentGroupItem).children) {
                return (
                  <div key={`group-${idx}`} className="relative">
                    {renderGroup(
                      child as SegmentGroupItem,
                      [...path, idx],
                      false
                    )}
                    {!readOnly && (
                      <button
                        type="button"
                        onClick={() => removeNodeFromGroup(path, idx)}
                        className="absolute top-5 right-3 p-1.5 text-slate-500 hover:text-rose-400 hover:bg-rose-500/10 rounded-lg transition-colors"
                        title="Remover Sub-Grupo"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    )}
                  </div>
                );
              }

              // Atomic Condition Item
              const condition = child as SegmentConditionItem;
              const fieldDef = allFields[condition.field] || {
                label: condition.field,
                type: "STRING",
                operators: ["equals", "not_equals"],
              };

              return (
                <div
                  key={`condition-${idx}`}
                  className="flex flex-col lg:flex-row lg:items-center justify-between gap-3 p-3 rounded-lg border border-slate-800/80 bg-[#0e1424] hover:border-slate-700 transition-colors"
                >
                  <div className="grid grid-cols-1 md:grid-cols-12 gap-2.5 flex-1 items-center">
                    {/* Campo */}
                    <div className="md:col-span-4">
                      {readOnly ? (
                        <div className="text-xs font-medium text-slate-300">
                          {fieldDef.label || condition.field}
                        </div>
                      ) : (
                        <select
                          value={condition.field}
                          onChange={(e) => {
                            const newField = e.target.value;
                            const newDef = allFields[newField];
                            const defaultOp = newDef?.operators?.[0] || "equals";
                            updateCondition(path, idx, {
                              field: newField,
                              operator: defaultOp,
                              value: "",
                            });
                          }}
                          className="w-full text-xs bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-slate-200 focus:outline-none focus:border-emerald-500"
                        >
                          {Object.entries(fieldCategories).map(([catKey, catFields]) => (
                            <optgroup
                              key={catKey}
                              label={
                                catKey === "player"
                                  ? "Dados do Jogador"
                                  : catKey === "tags"
                                  ? "Tags"
                                  : catKey === "consents"
                                  ? "Consentimentos (LGPD)"
                                  : catKey === "deposits"
                                  ? "Depósitos"
                                  : catKey === "bets"
                                  ? "Apostas"
                                  : catKey === "withdrawals"
                                  ? "Saques"
                                  : catKey === "logins"
                                  ? "Sessão / Login"
                                  : "Eventos"
                              }
                            >
                              {Object.entries(catFields).map(([fKey, fDef]) => (
                                <option key={fKey} value={fKey}>
                                  {fDef.label}
                                </option>
                              ))}
                            </optgroup>
                          ))}
                        </select>
                      )}
                    </div>

                    {/* Operador */}
                    <div className="md:col-span-3">
                      {readOnly ? (
                        <span className="text-xs text-emerald-400 font-semibold">
                          {allOperators[condition.operator]?.name || condition.operator}
                        </span>
                      ) : (
                        <select
                          value={condition.operator}
                          onChange={(e) =>
                            updateCondition(path, idx, { operator: e.target.value })
                          }
                          className="w-full text-xs bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-slate-200 focus:outline-none focus:border-emerald-500"
                        >
                          {(fieldDef.operators || Object.keys(allOperators)).map((opKey) => (
                            <option key={opKey} value={opKey}>
                              {allOperators[opKey]?.name || opKey}
                            </option>
                          ))}
                        </select>
                      )}
                    </div>

                    {/* Valor e Período */}
                    <div className="md:col-span-5 flex items-center gap-2">
                      {/* Se o operador for nullness (is_null, is_not_null), não precisa de valor */}
                      {["is_null", "is_not_null", "exists", "not_exists", "today", "yesterday"].includes(
                        condition.operator
                      ) ? (
                        <span className="text-xs text-slate-500 italic">
                          (Nenhum parâmetro requerido)
                        </span>
                      ) : fieldDef.options ? (
                        // Dropdown com opções pré-definidas (ex: Gênero, Evento, Status)
                        readOnly ? (
                          <span className="text-xs font-semibold text-slate-300">
                            {fieldDef.options[condition.value] || condition.value}
                          </span>
                        ) : (
                          <select
                            value={condition.value || ""}
                            onChange={(e) =>
                              updateCondition(path, idx, { value: e.target.value })
                            }
                            className="w-full text-xs bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-slate-200 focus:outline-none focus:border-emerald-500"
                          >
                            <option value="">Selecione uma opção...</option>
                            {Object.entries(fieldDef.options).map(([optVal, optLabel]) => (
                              <option key={optVal} value={optVal}>
                                {optLabel}
                              </option>
                            ))}
                          </select>
                        )
                      ) : (
                        // Input de texto / numérico genérico
                        readOnly ? (
                          <span className="text-xs text-slate-200 font-mono bg-slate-900 px-2 py-1 rounded border border-slate-800">
                            {String(condition.value ?? "")}
                          </span>
                        ) : (
                          <input
                            type={
                              ["INTEGER", "DECIMAL"].includes(fieldDef.type)
                                ? "number"
                                : fieldDef.type === "DATE"
                                ? "date"
                                : "text"
                            }
                            value={condition.value ?? ""}
                            onChange={(e) =>
                              updateCondition(path, idx, { value: e.target.value })
                            }
                            placeholder="Valor da regra..."
                            className="w-full text-xs bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-slate-200 focus:outline-none focus:border-emerald-500"
                          />
                        )
                      )}

                      {/* Janela Temporal (se suportada) */}
                      {fieldDef.supports_period && (
                        <div className="flex items-center gap-1.5 text-[11px] text-slate-400 whitespace-nowrap bg-slate-900/60 px-2 py-1 rounded border border-slate-800">
                          <Clock className="w-3 h-3 text-slate-500" />
                          <span>Janela:</span>
                          <input
                            type="number"
                            min="1"
                            value={condition.period_value ?? 30}
                            disabled={readOnly}
                            onChange={(e) =>
                              updateCondition(path, idx, {
                                period_value: Number(e.target.value),
                              })
                            }
                            className="w-12 text-xs bg-slate-950 border border-slate-700 rounded px-1.5 py-0.5 text-slate-200"
                          />
                          <select
                            value={condition.period_unit ?? "days"}
                            disabled={readOnly}
                            onChange={(e) =>
                              updateCondition(path, idx, {
                                period_unit: e.target.value,
                              })
                            }
                            className="text-xs bg-slate-950 border border-slate-700 rounded px-1.5 py-0.5 text-slate-200"
                          >
                            <option value="hours">horas</option>
                            <option value="days">dias</option>
                            <option value="weeks">semanas</option>
                            <option value="months">meses</option>
                          </select>
                        </div>
                      )}
                    </div>
                  </div>

                  {!readOnly && (
                    <button
                      type="button"
                      onClick={() => removeNodeFromGroup(path, idx)}
                      className="self-end lg:self-center p-1.5 text-slate-500 hover:text-rose-400 hover:bg-rose-500/10 rounded-lg transition-colors"
                      title="Excluir Condição"
                    >
                      <Trash2 className="w-3.5 h-3.5" />
                    </button>
                  )}
                </div>
              );
            })
          )}
        </div>
      </div>
    );
  };

  return (
    <div className="space-y-4">
      {/* Visual Rule Tree */}
      {renderGroup(rules, [], true)}

      {/* Live Preview Panel */}
      <div className="rounded-xl border border-slate-800 bg-[#0d1322] p-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
            <Users className="w-5 h-5" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <span className="text-xs font-semibold text-slate-300">
                Pré-visualização da Audiência em Tempo Real
              </span>
              {previewResult && (
                <span className="inline-flex items-center gap-1 text-[11px] text-slate-400">
                  <Clock className="w-3 h-3 text-slate-500" />
                  {previewResult.execution_time_ms} ms
                </span>
              )}
            </div>
            <div className="text-xs text-slate-500">
              {previewResult
                ? `${previewResult.count} jogadores qualificados encontrados`
                : "Clique no botão ao lado para simular o alcance deste segmento com a base atual."}
            </div>
          </div>
        </div>

        <div className="flex items-center gap-3">
          {previewResult && (
            <div className="text-right pr-2">
              <div className="text-xl font-bold text-emerald-400">
                {previewResult.count.toLocaleString("pt-BR")}
              </div>
              <div className="text-[10px] uppercase font-semibold text-slate-500">
                Membros
              </div>
            </div>
          )}

          <button
            type="button"
            onClick={handlePreview}
            disabled={previewLoading}
            className="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-lg bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white shadow-md disabled:opacity-50 transition-all"
          >
            {previewLoading ? (
              <>
                <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                Calculando...
              </>
            ) : (
              <>
                <Sparkles className="w-4 h-4" />
                Calcular Audiência
              </>
            )}
          </button>
        </div>
      </div>

      {previewError && (
        <div className="p-3 rounded-lg border border-rose-500/30 bg-rose-500/10 text-rose-300 text-xs flex items-center gap-2">
          <AlertCircle className="w-4 h-4 shrink-0 text-rose-400" />
          {previewError}
        </div>
      )}

      {/* Sample Preview Drawer/Table */}
      {previewResult && previewResult.sample.length > 0 && (
        <div className="rounded-xl border border-slate-800 bg-[#0d1322] p-4">
          <div className="text-xs font-semibold text-slate-300 mb-3 flex items-center gap-2">
            <CheckCircle2 className="w-4 h-4 text-emerald-400" />
            Amostra de Jogadores Qualificados ({previewResult.sample.length} de {previewResult.count}):
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="bg-slate-900/60 text-slate-400 border-b border-slate-800">
                <tr>
                  <th className="py-2 px-3">ID Externo</th>
                  <th className="py-2 px-3">Nome</th>
                  <th className="py-2 px-3">E-mail</th>
                  <th className="py-2 px-3">UF / Cidade</th>
                  <th className="py-2 px-3">Status</th>
                  <th className="py-2 px-3">Tags</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/60">
                {previewResult.sample.map((p: any) => (
                  <tr key={p.id} className="hover:bg-slate-900/40">
                    <td className="py-2 px-3 font-mono text-emerald-400 font-medium">
                      {p.external_id}
                    </td>
                    <td className="py-2 px-3 text-slate-200">{p.name}</td>
                    <td className="py-2 px-3 text-slate-400">{p.email || "—"}</td>
                    <td className="py-2 px-3 text-slate-400">
                      {p.state ? `${p.state}${p.city ? ` / ${p.city}` : ""}` : "—"}
                    </td>
                    <td className="py-2 px-3">
                      <span className="px-2 py-0.5 text-[10px] font-semibold rounded bg-slate-800 text-slate-300">
                        {p.status}
                      </span>
                    </td>
                    <td className="py-2 px-3">
                      {p.tags && p.tags.length > 0 ? (
                        <div className="flex flex-wrap gap-1">
                          {p.tags.map((t: any) => (
                            <span
                              key={t.id}
                              className="px-1.5 py-0.5 rounded text-[10px] font-medium"
                              style={{ backgroundColor: `${t.color || "#10b981"}20`, color: t.color || "#10b981" }}
                            >
                              {t.name}
                            </span>
                          ))}
                        </div>
                      ) : (
                        <span className="text-slate-600">—</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
}
