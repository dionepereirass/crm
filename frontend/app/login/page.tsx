"use client";

import React, { useState } from "react";
import { useRouter } from "next/navigation";
import { authService } from "@/services/auth-service";
import { Lock, Mail, ArrowRight, ShieldCheck, AlertCircle, Loader2 } from "lucide-react";

export default function LoginPage() {
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [loading, setLoading] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMessage(null);
    setLoading(true);

    try {
      await authService.login(email, password);
      router.push("/");
    } catch (err: any) {
      setErrorMessage(err.message || "Credenciais inválidas ou erro de conexão.");
    } finally {
      setLoading(false);
    }
  };

  const autofill = (userEmail: string) => {
    setEmail(userEmail);
    setPassword("Secret@123456");
    setErrorMessage(null);
  };

  return (
    <div className="min-h-screen bg-[#070b13] flex flex-col justify-center items-center p-6 relative overflow-hidden text-slate-100">
      {/* Background Glow */}
      <div className="absolute top-1/4 left-1/2 -translate-x-1/2 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none" />

      {/* Main Card */}
      <div className="w-full max-w-md bg-[#0d131f] border border-slate-800/90 rounded-2xl p-8 shadow-2xl relative z-10 space-y-6">
        {/* Header */}
        <div className="text-center space-y-2">
          <div className="inline-flex w-12 h-12 rounded-xl bg-emerald-500/20 border border-emerald-500/40 items-center justify-center font-black text-emerald-400 text-lg shadow-inner mb-1">
            BET
          </div>
          <h1 className="text-xl font-bold tracking-tight text-white">BET CRM</h1>
          <p className="text-xs text-slate-400">
            Acesso administrativo seguro com isolamento multi-plataforma
          </p>
        </div>

        {/* Error Notification */}
        {errorMessage && (
          <div className="p-3.5 rounded-lg bg-rose-500/10 border border-rose-500/30 flex items-start space-x-2.5 text-xs text-rose-300">
            <AlertCircle className="w-4 h-4 shrink-0 text-rose-400 mt-0.5" />
            <span>{errorMessage}</span>
          </div>
        )}

        {/* Login Form */}
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="space-y-1.5">
            <label className="block text-xs font-medium text-slate-300">E-mail Corporativo</label>
            <div className="relative">
              <Mail className="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500" />
              <input
                type="email"
                required
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="seu.email@empresa.com"
                className="w-full bg-slate-900/80 border border-slate-700/80 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-slate-100 text-xs rounded-lg pl-10 pr-3.5 py-2.5 transition outline-none placeholder:text-slate-600"
              />
            </div>
          </div>

          <div className="space-y-1.5">
            <label className="block text-xs font-medium text-slate-300">Senha de Acesso</label>
            <div className="relative">
              <Lock className="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500" />
              <input
                type="password"
                required
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="••••••••••••"
                className="w-full bg-slate-900/80 border border-slate-700/80 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 text-slate-100 text-xs rounded-lg pl-10 pr-3.5 py-2.5 transition outline-none placeholder:text-slate-600"
              />
            </div>
          </div>

          <button
            type="submit"
            disabled={loading}
            className="w-full bg-emerald-500 hover:bg-emerald-600 disabled:bg-emerald-800/60 text-slate-950 font-semibold py-2.5 rounded-lg text-xs flex items-center justify-center space-x-2 transition shadow-lg shadow-emerald-500/20 active:scale-[0.99]"
          >
            {loading ? (
              <>
                <Loader2 className="w-4 h-4 animate-spin" />
                <span>Autenticando...</span>
              </>
            ) : (
              <>
                <span>Entrar no Painel</span>
                <ArrowRight className="w-4 h-4" />
              </>
            )}
          </button>
        </form>

        {/* Quick Demo Credentials Autofill */}
        <div className="pt-4 border-t border-slate-800/80 space-y-2.5">
          <span className="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider text-center">
            Contas de Teste (FASE 2 Seeders)
          </span>
          <div className="grid grid-cols-2 gap-2 text-[10px]">
            <button
              type="button"
              onClick={() => autofill("superadmin@crm.example.com")}
              className="p-2 rounded border border-slate-800 hover:border-emerald-500/40 bg-slate-900/60 text-left transition hover:bg-slate-800/60"
            >
              <span className="font-semibold text-emerald-400 block">SUPER_ADMIN</span>
              <span className="text-slate-400 truncate block">superadmin@crm...</span>
            </button>

            <button
              type="button"
              onClick={() => autofill("admin@crm.example.com")}
              className="p-2 rounded border border-slate-800 hover:border-emerald-500/40 bg-slate-900/60 text-left transition hover:bg-slate-800/60"
            >
              <span className="font-semibold text-sky-400 block">ADMIN</span>
              <span className="text-slate-400 truncate block">admin@crm...</span>
            </button>

            <button
              type="button"
              onClick={() => autofill("marketing@crm.example.com")}
              className="p-2 rounded border border-slate-800 hover:border-emerald-500/40 bg-slate-900/60 text-left transition hover:bg-slate-800/60"
            >
              <span className="font-semibold text-violet-400 block">MARKETING</span>
              <span className="text-slate-400 truncate block">marketing@crm...</span>
            </button>

            <button
              type="button"
              onClick={() => autofill("analyst@crm.example.com")}
              className="p-2 rounded border border-slate-800 hover:border-emerald-500/40 bg-slate-900/60 text-left transition hover:bg-slate-800/60"
            >
              <span className="font-semibold text-amber-400 block">ANALYST</span>
              <span className="text-slate-400 truncate block">analyst@crm...</span>
            </button>
          </div>
        </div>

        {/* Security Footer */}
        <div className="flex items-center justify-center space-x-1.5 text-[10px] text-slate-500">
          <ShieldCheck className="w-3.5 h-3.5 text-emerald-400" />
          <span>Proteção por Sanctum, RBAC & Rate Limiting Ativo</span>
        </div>
      </div>
    </div>
  );
}
