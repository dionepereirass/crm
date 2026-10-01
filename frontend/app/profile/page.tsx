"use client";

import React, { useState } from "react";
import { useAuth } from "@/hooks/use-auth";
import Link from "next/link";
import {
  User,
  Shield,
  Key,
  Globe,
  ArrowLeft,
  LogOut,
  AlertTriangle,
  CheckCircle2,
  Lock,
} from "lucide-react";
import { authService } from "@/services/auth-service";

export default function ProfilePage() {
  const { user, activePlatform, loading, logout, switchPlatform } = useAuth(true);
  const [revoking, setRevoking] = useState(false);
  const [message, setMessage] = useState<string | null>(null);

  if (loading || !user) {
    return (
      <div className="min-h-screen bg-[#070b13] flex items-center justify-center text-slate-400 text-xs">
        <div className="animate-spin w-5 h-5 border-2 border-emerald-500 border-t-transparent rounded-full mr-2" />
        Carregando perfil...
      </div>
    );
  }

  const handleRevokeTokens = async () => {
    if (!confirm("Tem certeza que deseja revogar todas as sessões ativas? Você precisará fazer login novamente.")) {
      return;
    }
    setRevoking(true);
    try {
      await authService.revokeAllTokens();
      setMessage("Todos os tokens foram revogados. Redirecionando para login...");
      setTimeout(() => logout(), 1200);
    } catch (err: any) {
      setMessage(err.message || "Erro ao revogar tokens.");
    } finally {
      setRevoking(false);
    }
  };

  // Group permissions by category
  const permissionsByGroup = (user.permissions || []).reduce((acc: Record<string, typeof user.permissions>, perm) => {
    acc[perm.group] = acc[perm.group] || [];
    acc[perm.group].push(perm);
    return acc;
  }, {});

  return (
    <div className="min-h-screen bg-[#070b13] text-slate-100 p-8">
      <div className="max-w-4xl mx-auto space-y-6">
        {/* Top Header */}
        <div className="flex items-center justify-between pb-4 border-b border-slate-800">
          <Link
            href="/"
            className="flex items-center space-x-2 text-xs text-slate-400 hover:text-slate-100 transition"
          >
            <ArrowLeft className="w-4 h-4" />
            <span>Voltar ao Dashboard</span>
          </Link>

          <button
            onClick={logout}
            className="flex items-center space-x-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 border border-rose-500/30 transition"
          >
            <LogOut className="w-3.5 h-3.5" />
            <span>Encerrar Sessão</span>
          </button>
        </div>

        {message && (
          <div className="p-3.5 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-xs text-emerald-400 flex items-center space-x-2">
            <CheckCircle2 className="w-4 h-4 shrink-0" />
            <span>{message}</span>
          </div>
        )}

        {/* User Card */}
        <div className="p-6 rounded-2xl bg-[#0d131f] border border-slate-800/90 shadow-xl space-y-4">
          <div className="flex items-start justify-between">
            <div className="flex items-center space-x-4">
              <div className="w-14 h-14 rounded-2xl bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center font-bold text-xl text-emerald-400">
                {user.name.slice(0, 2).toUpperCase()}
              </div>
              <div>
                <h1 className="text-lg font-bold text-white">{user.name}</h1>
                <p className="text-xs text-slate-400">{user.email}</p>
                <div className="mt-2 flex items-center space-x-2">
                  <span className="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                    Status: {user.status}
                  </span>
                  {user.two_factor_enabled && (
                    <span className="px-2 py-0.5 rounded text-[10px] font-semibold bg-sky-500/20 text-sky-400 border border-sky-500/30">
                      2FA Ativado
                    </span>
                  )}
                </div>
              </div>
            </div>

            <button
              onClick={handleRevokeTokens}
              disabled={revoking}
              className="flex items-center space-x-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 transition"
            >
              <Lock className="w-3.5 h-3.5 text-amber-400" />
              <span>{revoking ? "Revogando..." : "Revogar Todos os Tokens"}</span>
            </button>
          </div>
        </div>

        {/* Roles & Accessible Platforms Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {/* Roles Card */}
          <div className="p-6 rounded-2xl bg-[#0d131f] border border-slate-800/90 shadow-xl space-y-3">
            <div className="flex items-center space-x-2 text-sm font-semibold text-white">
              <Shield className="w-4 h-4 text-emerald-400" />
              <span>Funções Atribuídas (Roles)</span>
            </div>
            <div className="flex flex-wrap gap-2 pt-2">
              {user.roles?.map((r) => (
                <span
                  key={r.id}
                  className="px-3 py-1.5 rounded-lg text-xs font-mono font-medium bg-slate-800 text-slate-200 border border-slate-700"
                >
                  {r.name} ({r.slug})
                </span>
              ))}
            </div>
          </div>

          {/* Accessible Platforms Card */}
          <div className="p-6 rounded-2xl bg-[#0d131f] border border-slate-800/90 shadow-xl space-y-3">
            <div className="flex items-center space-x-2 text-sm font-semibold text-white">
              <Globe className="w-4 h-4 text-sky-400" />
              <span>Plataformas Vinculadas</span>
            </div>
            <div className="space-y-2 pt-2">
              {user.platforms?.length === 0 ? (
                <p className="text-xs text-slate-500">Nenhuma plataforma atribuída.</p>
              ) : (
                user.platforms?.map((plat) => {
                  const isCurrent = activePlatform?.id === plat.id;
                  return (
                    <div
                      key={plat.id}
                      className={`p-3 rounded-lg border flex items-center justify-between text-xs transition ${
                        isCurrent
                          ? "bg-emerald-500/10 border-emerald-500/40 text-emerald-300"
                          : "bg-slate-900/60 border-slate-800 text-slate-300 hover:border-slate-700"
                      }`}
                    >
                      <div>
                        <span className="font-semibold block">{plat.name}</span>
                        <span className="text-[10px] text-slate-500 font-mono">slug: {plat.slug}</span>
                      </div>
                      {isCurrent ? (
                        <span className="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/20 text-emerald-400">
                          Ativa
                        </span>
                      ) : (
                        <button
                          onClick={() => switchPlatform(plat)}
                          className="px-2 py-1 rounded text-[10px] font-medium bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 transition"
                        >
                          Selecionar
                        </button>
                      )}
                    </div>
                  );
                })
              )}
            </div>
          </div>
        </div>

        {/* Permissions Breakdown */}
        <div className="p-6 rounded-2xl bg-[#0d131f] border border-slate-800/90 shadow-xl space-y-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-2 text-sm font-semibold text-white">
              <Key className="w-4 h-4 text-violet-400" />
              <span>Matriz de Permissões Granulares</span>
            </div>
            <span className="text-xs text-slate-400 font-mono">
              Total: {user.permissions?.length || 0} permissões
            </span>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 pt-2">
            {Object.entries(permissionsByGroup).map(([group, perms]) => (
              <div key={group} className="p-3.5 rounded-lg border border-slate-800/80 bg-slate-900/50 space-y-2">
                <span className="text-[11px] font-bold uppercase tracking-wider text-slate-400 block border-b border-slate-800 pb-1">
                  {group}
                </span>
                <ul className="space-y-1">
                  {perms.map((p) => (
                    <li key={p.id} className="text-xs text-slate-300 flex items-center space-x-1.5">
                      <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 shrink-0" />
                      <span className="truncate">{p.name}</span>
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
