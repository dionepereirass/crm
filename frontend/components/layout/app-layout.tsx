"use client";

import React, { useState } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useAuth } from "@/hooks/use-auth";
import {
  Users,
  Activity,
  Send,
  Mail,
  Layers,
  Tag,
  Zap,
  BarChart3,
  Globe,
  Webhook,
  Key,
  ShieldCheck,
  Settings,
  LogOut,
  ChevronDown,
  Bell,
} from "lucide-react";

interface AppLayoutProps {
  children: React.ReactNode;
  title?: string;
  badge?: string;
  actions?: React.ReactNode;
}

export function AppLayout({ children, title, badge, actions }: AppLayoutProps) {
  const pathname = usePathname();
  const { user, activePlatform, loading: authLoading, logout, switchPlatform } = useAuth(true);
  const [showPlatformDropdown, setShowPlatformDropdown] = useState(false);

  if (authLoading || !user) {
    return (
      <div className="min-h-screen bg-[#070b13] flex items-center justify-center text-slate-400 text-xs">
        <div className="animate-spin w-5 h-5 border-2 border-emerald-500 border-t-transparent rounded-full mr-2" />
        Verificando sessão segura...
      </div>
    );
  }

  const navItems = [
    { id: "dashboard", label: "Dashboard", href: "/", icon: BarChart3 },
    { id: "players", label: "Jogadores", href: "/players", icon: Users },
    { id: "events", label: "Eventos", href: "/events", icon: Activity },
    { id: "segments", label: "Segmentos", href: "/segments", icon: Layers },
    { id: "tags", label: "Tags", href: "/tags", icon: Tag },
    { id: "campaigns", label: "Campanhas", href: "/campaigns", icon: Send },
    { id: "analytics", label: "Analytics", href: "/analytics", icon: BarChart3 },
    { id: "templates", label: "Templates", href: "/templates", icon: Mail },
    { id: "providers", label: "Provedores", href: "/providers", icon: Globe },
    { id: "messages", label: "Mensagens", href: "/messages", icon: Send },
    { id: "automations", label: "Automações", href: "/automations", icon: Zap },
    { id: "reports", label: "Relatórios", href: "/reports", icon: BarChart3 },
    { id: "alerts", label: "Alertas", href: "/alerts", icon: Bell },
    { id: "integrations", label: "Integrações", href: "/integrations", icon: Globe },
    { id: "webhooks", label: "Webhooks", href: "/webhooks", icon: Webhook },
    { id: "api", label: "API & Chaves", href: "/api", icon: Key },
    { id: "users", label: "Usuários", href: "/users", icon: Users },
    { id: "privacy", label: "Privacidade & LGPD", href: "/privacy", icon: ShieldCheck },
    { id: "audit", label: "Auditoria", href: "/privacy/audit", icon: ShieldCheck },
    { id: "settings", label: "Configurações", href: "/settings", icon: Settings },
  ];

  return (
    <div className="flex min-h-screen bg-[#090d16] text-slate-100">
      {/* Sidebar */}
      <aside className="w-64 border-r border-slate-800 bg-[#0d131f] flex flex-col justify-between shrink-0">
        <div>
          {/* Logo Header */}
          <div className="p-6 border-b border-slate-800/80 flex items-center justify-between">
            <Link href="/" className="flex items-center space-x-3">
              <div className="w-9 h-9 rounded-lg bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center font-black text-emerald-400">
                BET
              </div>
              <div>
                <h1 className="font-bold text-sm tracking-wide text-white">BET CRM</h1>
                <p className="text-[11px] text-emerald-400 font-mono">FASE 12 • DASHBOARD & ANALYTICS</p>
              </div>
            </Link>
          </div>

          {/* Active Platform Selector */}
          <div className="p-4 relative">
            <div className="bg-slate-900/90 rounded-lg p-2.5 border border-slate-800 space-y-1">
              <div className="flex items-center justify-between">
                <span className="text-slate-400 text-[10px] uppercase font-semibold">Plataforma Atual</span>
                <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
              </div>
              <button
                type="button"
                onClick={() => setShowPlatformDropdown(!showPlatformDropdown)}
                className="w-full flex items-center justify-between text-xs font-semibold text-slate-200 hover:text-white transition"
              >
                <span className="truncate">{activePlatform?.name || "Todas as Plataformas"}</span>
                <ChevronDown className="w-3.5 h-3.5 text-slate-400 ml-1 shrink-0" />
              </button>

              {/* Platform Switcher Dropdown */}
              {showPlatformDropdown && user.platforms?.length > 1 && (
                <div className="absolute left-4 right-4 top-16 bg-[#0f172a] border border-slate-700 rounded-lg p-1.5 shadow-2xl z-50 space-y-1">
                  <span className="text-[10px] text-slate-400 px-2 py-0.5 block font-medium">Trocar Plataforma:</span>
                  {user.platforms.map((plat) => (
                    <button
                      key={plat.id}
                      type="button"
                      onClick={() => {
                        switchPlatform(plat);
                        setShowPlatformDropdown(false);
                      }}
                      className={`w-full text-left px-2 py-1.5 rounded text-xs transition ${
                        activePlatform?.id === plat.id
                          ? "bg-emerald-500/20 text-emerald-300 font-semibold"
                          : "text-slate-300 hover:bg-slate-800"
                      }`}
                    >
                      {plat.name}
                    </button>
                  ))}
                </div>
              )}
            </div>
          </div>

          {/* Navigation Links */}
          <nav className="px-3 py-2 space-y-1">
            {navItems.map((item) => {
              const Icon = item.icon;
              const isActive =
                item.href === "/"
                  ? pathname === "/"
                  : pathname === item.href || pathname.startsWith(`${item.href}/`);
              return (
                <Link
                  key={item.id}
                  href={item.href}
                  className={`w-full flex items-center space-x-3 px-3 py-2 rounded-md text-xs font-medium transition-colors ${
                    isActive
                      ? "bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 font-semibold"
                      : "text-slate-400 hover:text-slate-200 hover:bg-slate-800/50"
                  }`}
                >
                  <Icon className="w-4 h-4 shrink-0" />
                  <span>{item.label}</span>
                </Link>
              );
            })}
          </nav>
        </div>

        {/* User Footer Profile */}
        <div className="p-4 border-t border-slate-800/80 bg-slate-950/40 space-y-2.5">
          <Link
            href="/profile"
            className="flex items-center space-x-3 p-2 rounded-lg hover:bg-slate-800/60 transition group"
          >
            <div className="w-8 h-8 rounded-lg bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center font-bold text-xs text-emerald-400 shrink-0">
              {user.name.slice(0, 2).toUpperCase()}
            </div>
            <div className="min-w-0 flex-1">
              <span className="block text-xs font-semibold text-white truncate group-hover:text-emerald-400 transition">
                {user.name}
              </span>
              <span className="block text-[10px] text-slate-400 truncate font-mono">
                {user.roles?.[0]?.slug || "USER"}
              </span>
            </div>
          </Link>

          <button
            onClick={logout}
            type="button"
            className="w-full flex items-center justify-center space-x-1.5 py-1.5 rounded-md text-[11px] font-medium text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 border border-transparent hover:border-rose-500/20 transition"
          >
            <LogOut className="w-3.5 h-3.5" />
            <span>Sair</span>
          </button>
        </div>
      </aside>

      {/* Main Content Area */}
      <main className="flex-1 flex flex-col min-w-0 overflow-y-auto">
        {/* Top Navbar */}
        <header className="h-16 border-b border-slate-800/80 bg-[#0d131f]/70 backdrop-blur px-8 flex items-center justify-between shrink-0">
          <div className="flex items-center space-x-4">
            <h2 className="text-base font-semibold text-white">{title || "Painel"}</h2>
            {badge && (
              <span className="px-2.5 py-0.5 rounded-full text-[11px] font-mono bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                {badge}
              </span>
            )}
            <span className="px-2.5 py-0.5 rounded-full text-[11px] font-mono bg-sky-500/10 text-sky-400 border border-sky-500/20 hidden sm:inline-block">
              {activePlatform?.name || "Isolamento Multitenant"}
            </span>
          </div>

          <div className="flex items-center space-x-3">{actions}</div>
        </header>

        {/* Page Body */}
        <div className="p-8 max-w-7xl w-full">{children}</div>
      </main>
    </div>
  );
}
