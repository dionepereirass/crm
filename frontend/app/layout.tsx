import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "BET CRM — Painel de Gestão e Mensageria",
  description: "Plataforma SaaS de Gestão de Clientes, Segmentação e Automações para Gaming & Apostas",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="pt-BR">
      <body className="antialiased bg-[#090d16] text-slate-100 min-h-screen">
        {children}
      </body>
    </html>
  );
}
