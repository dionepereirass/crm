"use client";

import React, { useEffect, useState, use } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { AppLayout } from "@/components/layout/app-layout";
import {
  campaignService,
  Campaign,
  CampaignFormData,
} from "@/services/campaign-service";
import {
  ArrowLeft,
  Save,
  RefreshCw,
  AlertTriangle,
  CheckCircle2,
  Edit,
  Clock,
  Mail,
  Users,
} from "lucide-react";

export default function EditCampaignPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = use(params);
  const router = useRouter();

  const [campaign, setCampaign] = useState<Campaign | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [scheduledAt, setScheduledAt] = useState("");
  const [fromName, setFromName] = useState("");
  const [fromEmail, setFromEmail] = useState("");

  useEffect(() => {
    const fetchCampaign = async () => {
      try {
        setLoading(true);
        const data = await campaignService.getById(id);
        setCampaign(data);
        setName(data.name);
        setDescription(data.description || "");
        setScheduledAt(
          data.scheduled_at ? new Date(data.scheduled_at).toISOString().slice(0, 16) : ""
        );
        setFromName(data.from_name || "");
        setFromEmail(data.from_email || "");
      } catch (err: any) {
        setError(err.message || "Erro ao carregar campanha.");
      } finally {
        setLoading(false);
      }
    };

    fetchCampaign();
  }, [id]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) return;

    try {
      setSaving(true);
      setError(null);
      setSuccess(null);

      await campaignService.update(id, {
        name,
        description: description || undefined,
        scheduled_at: scheduledAt ? new Date(scheduledAt).toISOString() : null,
        from_name: fromName || undefined,
        from_email: fromEmail || undefined,
      });

      setSuccess("Campanha atualizada com sucesso!");
      setTimeout(() => {
        router.push(`/campaigns/${id}`);
      }, 1000);
    } catch (err: any) {
      setError(err.message || "Erro ao salvar alterações da campanha.");
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <AppLayout>
        <div className="py-24 text-center text-gray-400">
          <RefreshCw className="w-8 h-8 animate-spin mx-auto text-emerald-500 mb-3" />
          <p>Carregando campanha...</p>
        </div>
      </AppLayout>
    );
  }

  if (!campaign) {
    return (
      <AppLayout>
        <div className="p-8 text-center text-gray-400">Campanha não encontrada.</div>
      </AppLayout>
    );
  }

  const isEditable = ["DRAFT", "READY"].includes(campaign.status);

  return (
    <AppLayout>
      <div className="max-w-3xl mx-auto space-y-6">
        <div className="flex items-center gap-3">
          <Link
            href={`/campaigns/${id}`}
            className="p-2 hover:bg-gray-800 rounded-lg text-gray-400 hover:text-white transition-colors"
          >
            <ArrowLeft className="w-5 h-5" />
          </Link>
          <div>
            <h1 className="text-2xl font-bold text-white tracking-tight flex items-center gap-2">
              <Edit className="w-6 h-6 text-emerald-500" />
              Editar Campanha #{id}
            </h1>
            <p className="text-xs text-gray-400 mt-0.5">
              Status atual: <span className="font-semibold text-gray-300">{campaign.status}</span> • Canal:{" "}
              <span className="font-semibold text-gray-300">{campaign.channel}</span>
            </p>
          </div>
        </div>

        {!isEditable && (
          <div className="p-4 bg-amber-950/60 border border-amber-800 rounded-xl text-amber-200 text-sm flex items-center gap-2">
            <AlertTriangle className="w-5 h-5 flex-shrink-0 text-amber-400" />
            <span>
              Campanhas em status <strong>{campaign.status}</strong> não podem ter seus parâmetros alterados para preservar a integridade do histórico de disparos.
            </span>
          </div>
        )}

        {error && (
          <div className="p-4 bg-rose-950/60 border border-rose-800 rounded-xl text-rose-200 text-sm flex items-center gap-2">
            <AlertTriangle className="w-5 h-5 flex-shrink-0 text-rose-400" />
            <span>{error}</span>
          </div>
        )}

        {success && (
          <div className="p-4 bg-emerald-950/60 border border-emerald-800 rounded-xl text-emerald-200 text-sm flex items-center gap-2">
            <CheckCircle2 className="w-5 h-5 flex-shrink-0 text-emerald-400" />
            <span>{success}</span>
          </div>
        )}

        <form onSubmit={handleSubmit} className="p-6 bg-gray-900 border border-gray-800 rounded-2xl space-y-5">
          <div>
            <label className="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">
              Nome da Campanha *
            </label>
            <input
              type="text"
              required
              disabled={!isEditable || saving}
              value={name}
              onChange={(e) => setName(e.target.value)}
              className="w-full px-4 py-2.5 bg-gray-950 border border-gray-800 rounded-lg text-sm text-gray-100 focus:outline-none focus:border-emerald-500 disabled:opacity-50"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">
              Descrição
            </label>
            <textarea
              rows={3}
              disabled={!isEditable || saving}
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              className="w-full px-4 py-2.5 bg-gray-950 border border-gray-800 rounded-lg text-sm text-gray-100 focus:outline-none focus:border-emerald-500 disabled:opacity-50"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">
              Agendamento (Opcional)
            </label>
            <input
              type="datetime-local"
              disabled={!isEditable || saving}
              value={scheduledAt}
              onChange={(e) => setScheduledAt(e.target.value)}
              className="w-full px-4 py-2.5 bg-gray-950 border border-gray-800 rounded-lg text-sm text-gray-100 focus:outline-none focus:border-purple-500 disabled:opacity-50"
            />
            <span className="text-[11px] text-gray-500 mt-1 block">Deixe em branco para disparo imediato.</span>
          </div>

          {campaign.channel === "EMAIL" && (
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-3 border-t border-gray-800">
              <div>
                <label className="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">
                  Nome do Remetente
                </label>
                <input
                  type="text"
                  disabled={!isEditable || saving}
                  value={fromName}
                  onChange={(e) => setFromName(e.target.value)}
                  className="w-full px-3 py-2 bg-gray-950 border border-gray-800 rounded-lg text-sm text-gray-100 focus:outline-none focus:border-emerald-500 disabled:opacity-50"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">
                  E-mail do Remetente
                </label>
                <input
                  type="email"
                  disabled={!isEditable || saving}
                  value={fromEmail}
                  onChange={(e) => setFromEmail(e.target.value)}
                  className="w-full px-3 py-2 bg-gray-950 border border-gray-800 rounded-lg text-sm text-gray-100 focus:outline-none focus:border-emerald-500 disabled:opacity-50"
                />
              </div>
            </div>
          )}

          <div className="flex items-center justify-end gap-3 pt-4 border-t border-gray-800">
            <Link
              href={`/campaigns/${id}`}
              className="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 text-sm font-medium rounded-lg transition-colors"
            >
              Cancelar
            </Link>

            <button
              type="submit"
              disabled={!isEditable || saving}
              className="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-lg transition-colors flex items-center gap-2 disabled:opacity-50"
            >
              <Save className="w-4 h-4" />
              {saving ? "Salvando..." : "Salvar Alterações"}
            </button>
          </div>
        </form>
      </div>
    </AppLayout>
  );
}
