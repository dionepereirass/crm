"use client";

import React, { useEffect } from "react";
import { AlertCircle, RefreshCw } from "lucide-react";

export default function ErrorBoundary({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    console.error("Client Error Boundary caught:", error);
  }, [error]);

  return (
    <div className="min-h-screen bg-[#070b13] flex items-center justify-center p-6 text-slate-100">
      <div className="max-w-md w-full bg-[#0d131f] border border-slate-800 rounded-2xl p-8 text-center space-y-5 shadow-2xl">
        <div className="w-12 h-12 rounded-xl bg-rose-500/20 border border-rose-500/40 flex items-center justify-center mx-auto text-rose-400">
          <AlertCircle className="w-6 h-6" />
        </div>

        <div className="space-y-1.5">
          <h2 className="text-base font-bold text-white">Falha ao Renderizar Página</h2>
          <p className="text-xs text-slate-400">
            {error?.message || "Ocorreu uma exceção não tratada na interface."}
          </p>
        </div>

        <div className="pt-2">
          <button
            onClick={() => reset()}
            type="button"
            className="w-full bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-semibold py-2.5 rounded-lg text-xs flex items-center justify-center space-x-2 transition shadow-lg shadow-emerald-500/20"
          >
            <RefreshCw className="w-4 h-4" />
            <span>Tentar Novamente</span>
          </button>
        </div>
      </div>
    </div>
  );
}
