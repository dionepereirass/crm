"use client";

import React, { useState } from "react";
import { Monitor, Smartphone, Mail, Eye } from "lucide-react";

interface EmailPreviewPaneProps {
  subject?: string;
  preheader?: string;
  htmlContent?: string;
  textContent?: string;
}

export function EmailPreviewPane({
  subject = "",
  preheader = "",
  htmlContent = "",
  textContent = "",
}: EmailPreviewPaneProps) {
  const [device, setDevice] = useState<"desktop" | "mobile">("desktop");
  const [viewMode, setViewMode] = useState<"html" | "text">("html");

  return (
    <div className="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden flex flex-col h-full shadow-lg">
      {/* Toolbar */}
      <div className="px-4 py-2.5 border-b border-slate-800 flex items-center justify-between bg-slate-950/70">
        <div className="flex items-center space-x-2">
          <Eye className="w-4 h-4 text-emerald-400" />
          <span className="text-xs font-semibold text-slate-200">
            Pré-visualização em Tempo Real
          </span>
        </div>

        <div className="flex items-center space-x-2">
          {/* HTML vs Plain Text Tab */}
          <div className="bg-slate-900 border border-slate-800 rounded-lg p-0.5 flex space-x-0.5 text-xs">
            <button
              type="button"
              onClick={() => setViewMode("html")}
              className={`px-2 py-1 rounded transition text-[11px] ${
                viewMode === "html"
                  ? "bg-slate-800 text-white font-medium shadow"
                  : "text-slate-400 hover:text-slate-200"
              }`}
            >
              HTML
            </button>
            <button
              type="button"
              onClick={() => setViewMode("text")}
              className={`px-2 py-1 rounded transition text-[11px] ${
                viewMode === "text"
                  ? "bg-slate-800 text-white font-medium shadow"
                  : "text-slate-400 hover:text-slate-200"
              }`}
            >
              Texto Puro
            </button>
          </div>

          {/* Desktop vs Mobile */}
          <div className="bg-slate-900 border border-slate-800 rounded-lg p-0.5 flex space-x-0.5 text-xs">
            <button
              type="button"
              onClick={() => setDevice("desktop")}
              className={`p-1 rounded transition ${
                device === "desktop"
                  ? "bg-emerald-500/20 text-emerald-400 border border-emerald-500/30"
                  : "text-slate-400 hover:text-slate-200"
              }`}
              title="Desktop (600px)"
            >
              <Monitor className="w-4 h-4" />
            </button>
            <button
              type="button"
              onClick={() => setDevice("mobile")}
              className={`p-1 rounded transition ${
                device === "mobile"
                  ? "bg-emerald-500/20 text-emerald-400 border border-emerald-500/30"
                  : "text-slate-400 hover:text-slate-200"
              }`}
              title="Mobile (375px)"
            >
              <Smartphone className="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>

      {/* Email Client Header Simulator (Inbox Preview) */}
      <div className="p-4 border-b border-slate-800/80 bg-slate-950/40 space-y-1.5 text-xs">
        <div className="flex items-center space-x-2 text-slate-400">
          <Mail className="w-3.5 h-3.5 text-slate-500" />
          <span className="font-semibold text-slate-300">De:</span>
          <span>Bet CRM &lt;comunicacao@betbrasil.com&gt;</span>
        </div>
        <div className="flex items-baseline space-x-2">
          <span className="font-semibold text-slate-300">Assunto:</span>
          <span className="text-white font-medium">
            {subject || <span className="text-slate-500 italic">Sem assunto</span>}
          </span>
        </div>
        {preheader && (
          <div className="flex items-baseline space-x-2 text-slate-400 text-[11px]">
            <span className="font-semibold text-slate-500">Pré-cabeçalho:</span>
            <span>{preheader}</span>
          </div>
        )}
      </div>

      {/* Viewport Canvas */}
      <div className="flex-1 bg-slate-950/80 overflow-y-auto p-4 flex justify-center items-start min-h-[400px]">
        {viewMode === "html" ? (
          <div
            className={`transition-all duration-200 bg-white text-slate-900 rounded-lg shadow-xl overflow-hidden ${
              device === "desktop" ? "w-full max-w-[620px]" : "w-[375px]"
            }`}
          >
            {htmlContent ? (
              <div
                className="p-6 prose prose-sm max-w-none text-slate-800"
                dangerouslySetInnerHTML={{ __html: htmlContent }}
              />
            ) : (
              <div className="py-20 px-6 text-center text-slate-400 text-xs italic">
                Nenhum conteúdo HTML para exibir. Digite no editor ao lado para visualizar aqui.
              </div>
            )}
          </div>
        ) : (
          <div
            className={`transition-all duration-200 bg-slate-900 border border-slate-800 text-slate-200 rounded-lg p-4 font-mono text-xs whitespace-pre-wrap ${
              device === "desktop" ? "w-full max-w-[620px]" : "w-[375px]"
            }`}
          >
            {textContent || (
              <span className="text-slate-500 italic">
                Nenhum texto puro configurado.
              </span>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
