"use client";

import React, { useState, useEffect, useCallback } from "react";
import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { AppLayout } from "@/components/layout/app-layout";
import { playerService, Player } from "@/services/player-service";
import { useAuth } from "@/hooks/use-auth";
import {
  ArrowLeft,
  Save,
  AlertCircle,
  RefreshCw,
  CheckCircle2,
} from "lucide-react";

export default function EditPlayerPage() {
  const params = useParams();
  const router = useRouter();
  const id = params?.id as string;
  const { activePlatform } = useAuth(true);

  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});

  const [formData, setFormData] = useState({
    external_id: "",
    name: "",
    email: "",
    phone: "",
    cpf: "",
    birth_date: "",
    gender: "M",
    state: "",
    city: "",
    affiliate: "",
    status: "active",
  });

  const [customFields, setCustomFields] = useState<Array<{ key: string; value: string }>>([]);

  const loadPlayer = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const p = await playerService.getPlayer(id);
      setFormData({
        external_id: p.external_id || "",
        name: p.name || "",
        email: p.email || "",
        phone: p.phone || "",
        cpf: p.cpf || "",
        birth_date: p.birth_date ? p.birth_date.split("T")[0] : "",
        gender: p.gender || "M",
        state: p.state || "",
        city: p.city || "",
        affiliate: p.affiliate || "",
        status: p.status || "active",
      });

      if (p.custom_fields && typeof p.custom_fields === "object") {
        setCustomFields(
          Object.entries(p.custom_fields).map(([k, v]) => ({
            key: k,
            value: typeof v === "object" ? JSON.stringify(v) : String(v),
          }))
        );
      }
    } catch (err: any) {
      setError(err.message || "Erro ao carregar dados do jogador.");
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    if (activePlatform && id) {
      loadPlayer();
    }
  }, [activePlatform, id, loadPlayer]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
  };

  const handleCustomFieldChange = (index: number, field: "key" | "value", val: string) => {
    setCustomFields((prev) => {
      const updated = [...prev];
      updated[index][field] = val;
      return updated;
    });
  };

  const addCustomField = () => {
    setCustomFields((prev) => [...prev, { key: "", value: "" }]);
  };

  const removeCustomField = (index: number) => {
    setCustomFields((prev) => prev.filter((_, i) => i !== index));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setError(null);
    setFieldErrors({});

    try {
      const cfObject: Record<string, any> = {};
      customFields.forEach(({ key, value }) => {
        if (key.trim()) {
          cfObject[key.trim()] = value;
        }
      });

      const payload = {
        name: formData.name,
        email: formData.email,
        phone: formData.phone || null,
        cpf: formData.cpf || null,
        birth_date: formData.birth_date || null,
        gender: formData.gender,
        state: formData.state || null,
        city: formData.city || null,
        affiliate: formData.affiliate || null,
        status: formData.status as any,
        custom_fields: cfObject,
      };

      await playerService.updatePlayer(id, payload);
      router.push(`/players/${id}`);
    } catch (err: any) {
      setError(err.message || "Erro ao atualizar jogador.");
      if (err.errors) {
        setFieldErrors(err.errors);
      }
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <AppLayout title="Editar Jogador">
        <div className="py-24 flex flex-col items-center justify-center text-slate-400 space-y-3">
          <div className="animate-spin w-8 h-8 border-2 border-emerald-500 border-t-transparent rounded-full" />
          <p className="text-xs">Carregando dados para edição...</p>
        </div>
      </AppLayout>
    );
  }

  return (
    <AppLayout
      title={`Editar Jogador #${id}`}
      badge={`ID: ${formData.external_id}`}
      actions={
        <Link
          href={`/players/${id}`}
          className="flex items-center space-x-1.5 px-3 py-1.5 rounded-md text-xs font-medium bg-slate-800 hover:bg-slate-700 text-slate-200 transition border border-slate-700"
        >
          <ArrowLeft className="w-3.5 h-3.5" />
          <span>Voltar para Ficha 360°</span>
        </Link>
      }
    >
      <form onSubmit={handleSubmit} className="space-y-6 max-w-4xl">
        {error && (
          <div className="p-4 rounded-xl border border-rose-500/30 bg-rose-500/10 text-rose-300 text-xs flex items-center space-x-2">
            <AlertCircle className="w-4 h-4 shrink-0" />
            <span>{error}</span>
          </div>
        )}

        {/* 1. Identification Section */}
        <div className="p-6 rounded-xl border border-slate-800 bg-[#0d131f] space-y-4">
          <div className="flex items-center space-x-3 pb-3 border-b border-slate-800">
            <div className="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-bold">
              1
            </div>
            <div>
              <h3 className="text-sm font-semibold text-white">Identificação & Status</h3>
              <p className="text-xs text-slate-400">
                O ID Externo é a chave de integração da plataforma da operadora (imutável após criação).
              </p>
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
              <label className="block text-xs font-medium text-slate-400 mb-1">ID Externo</label>
              <input
                type="text"
                disabled
                value={formData.external_id}
                className="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-xs text-slate-400 cursor-not-allowed font-mono"
              />
            </div>

            <div>
              <label className="block text-xs font-medium text-slate-300 mb-1">
                Nome Completo <span className="text-rose-400">*</span>
              </label>
              <input
                type="text"
                name="name"
                required
                value={formData.name}
                onChange={handleChange}
                className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500 transition"
              />
              {fieldErrors.name && (
                <p className="text-[11px] text-rose-400 mt-1">{fieldErrors.name[0]}</p>
              )}
            </div>

            <div>
              <label className="block text-xs font-medium text-slate-300 mb-1">
                Status <span className="text-rose-400">*</span>
              </label>
              <select
                name="status"
                value={formData.status}
                onChange={handleChange}
                className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500 transition"
              >
                <option value="active">Ativo</option>
                <option value="inactive">Inativo</option>
                <option value="churned">Churned</option>
                <option value="blocked">Bloqueado</option>
                <option value="pending">Pendente</option>
              </select>
            </div>
          </div>
        </div>

        {/* 2. Contact Data */}
        <div className="p-6 rounded-xl border border-slate-800 bg-[#0d131f] space-y-4">
          <div className="flex items-center space-x-3 pb-3 border-b border-slate-800">
            <div className="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-bold">
              2
            </div>
            <div>
              <h3 className="text-sm font-semibold text-white">Canais de Contato & LGPD</h3>
              <p className="text-xs text-slate-400">Informações de contato e documento do apostador.</p>
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
              <label className="block text-xs font-medium text-slate-300 mb-1">
                E-mail <span className="text-rose-400">*</span>
              </label>
              <input
                type="email"
                name="email"
                required
                value={formData.email}
                onChange={handleChange}
                className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500 transition font-mono"
              />
              {fieldErrors.email && (
                <p className="text-[11px] text-rose-400 mt-1">{fieldErrors.email[0]}</p>
              )}
            </div>

            <div>
              <label className="block text-xs font-medium text-slate-300 mb-1">Telefone / Celular</label>
              <input
                type="text"
                name="phone"
                value={formData.phone}
                onChange={handleChange}
                className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500 transition font-mono"
              />
            </div>

            <div>
              <label className="block text-xs font-medium text-slate-300 mb-1">CPF</label>
              <input
                type="text"
                name="cpf"
                value={formData.cpf}
                onChange={handleChange}
                className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500 transition font-mono"
              />
            </div>
          </div>
        </div>

        {/* 3. Demographic & Acquisition */}
        <div className="p-6 rounded-xl border border-slate-800 bg-[#0d131f] space-y-4">
          <div className="flex items-center space-x-3 pb-3 border-b border-slate-800">
            <div className="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-bold">
              3
            </div>
            <div>
              <h3 className="text-sm font-semibold text-white">Demografia & Aquisição</h3>
              <p className="text-xs text-slate-400">
                Segmentação por localização e rastreamento de afiliados.
              </p>
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
              <label className="block text-xs font-medium text-slate-300 mb-1">Data de Nascimento</label>
              <input
                type="date"
                name="birth_date"
                value={formData.birth_date}
                onChange={handleChange}
                className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500 transition"
              />
            </div>

            <div>
              <label className="block text-xs font-medium text-slate-300 mb-1">Gênero</label>
              <select
                name="gender"
                value={formData.gender}
                onChange={handleChange}
                className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500 transition"
              >
                <option value="M">Masculino</option>
                <option value="F">Feminino</option>
                <option value="OTHER">Outro</option>
              </select>
            </div>

            <div>
              <label className="block text-xs font-medium text-slate-300 mb-1">Estado (UF)</label>
              <input
                type="text"
                name="state"
                maxLength={2}
                value={formData.state}
                onChange={(e) => setFormData((prev) => ({ ...prev, state: e.target.value.toUpperCase() }))}
                className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white uppercase focus:outline-none focus:border-emerald-500 transition"
              />
            </div>

            <div>
              <label className="block text-xs font-medium text-slate-300 mb-1">Cidade</label>
              <input
                type="text"
                name="city"
                value={formData.city}
                onChange={handleChange}
                className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500 transition"
              />
            </div>

            <div className="sm:col-span-2 lg:col-span-4">
              <label className="block text-xs font-medium text-slate-300 mb-1">Afiliado / Origem</label>
              <input
                type="text"
                name="affiliate"
                value={formData.affiliate}
                onChange={handleChange}
                className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white focus:outline-none focus:border-emerald-500 transition"
              />
            </div>
          </div>
        </div>

        {/* 4. Custom Fields */}
        <div className="p-6 rounded-xl border border-slate-800 bg-[#0d131f] space-y-4">
          <div className="flex items-center justify-between pb-3 border-b border-slate-800">
            <div className="flex items-center space-x-3">
              <div className="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-bold">
                4
              </div>
              <div>
                <h3 className="text-sm font-semibold text-white">Campos Customizados (JSONB)</h3>
                <p className="text-xs text-slate-400">Metadados dinâmicos e propriedades específicas da bet.</p>
              </div>
            </div>

            <button
              type="button"
              onClick={addCustomField}
              className="px-2.5 py-1 rounded bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 text-xs font-medium transition"
            >
              + Adicionar Campo
            </button>
          </div>

          <div className="space-y-2">
            {customFields.map((cf, idx) => (
              <div key={idx} className="flex items-center space-x-2">
                <input
                  type="text"
                  placeholder="Chave"
                  value={cf.key}
                  onChange={(e) => handleCustomFieldChange(idx, "key", e.target.value)}
                  className="w-1/3 px-3 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white placeholder-slate-500 font-mono"
                />
                <input
                  type="text"
                  placeholder="Valor"
                  value={cf.value}
                  onChange={(e) => handleCustomFieldChange(idx, "value", e.target.value)}
                  className="flex-1 px-3 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white placeholder-slate-500"
                />
                <button
                  type="button"
                  onClick={() => removeCustomField(idx)}
                  className="px-2 py-1 text-slate-500 hover:text-rose-400 text-xs transition"
                >
                  ✕
                </button>
              </div>
            ))}
          </div>
        </div>

        {/* Submit Actions */}
        <div className="flex items-center justify-end space-x-3 pt-4">
          <Link
            href={`/players/${id}`}
            className="px-4 py-2 rounded-lg text-xs font-medium text-slate-400 hover:bg-slate-800 border border-slate-700 transition"
          >
            Cancelar
          </Link>

          <button
            type="submit"
            disabled={saving}
            className="flex items-center space-x-2 px-5 py-2 rounded-lg text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white transition shadow-lg shadow-emerald-950/40"
          >
            {saving ? (
              <div className="animate-spin w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full" />
            ) : (
              <Save className="w-3.5 h-3.5" />
            )}
            <span>{saving ? "Salvando..." : "Salvar Alterações"}</span>
          </button>
        </div>
      </form>
    </AppLayout>
  );
}
