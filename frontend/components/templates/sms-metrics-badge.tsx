"use client";

import React, { useMemo } from "react";
import { AlertTriangle, CheckCircle, Info } from "lucide-react";

interface SmsMetricsBadgeProps {
  content: string;
}

export function SmsMetricsBadge({ content }: SmsMetricsBadgeProps) {
  // GSM-7 Basic character set + extension characters
  const metrics = useMemo(() => {
    const text = content || "";
    const charCount = text.length;

    // GSM 7-bit default alphabet + extension table
    const gsm7Regex = /^[@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞ\x1BÆæßÉ !"#¤%&'()*+,\-.\/0-9:;<=>?¡A-ZÄÖÑÜ§¿a-zäöñüà\^{}\\\[~\]|€]*$/;

    const isGsm7 = gsm7Regex.test(text);
    const encoding: "GSM-7" | "UNICODE" = isGsm7 ? "GSM-7" : "UNICODE";

    // Non-GSM characters
    const nonGsmChars = isGsm7
      ? []
      : Array.from(new Set(text.split("").filter((c) => !gsm7Regex.test(c))));

    let segments = 1;
    let segmentLimit = 160;
    let charsRemaining = 160;

    if (charCount === 0) {
      segments = 0;
      charsRemaining = 160;
    } else if (encoding === "GSM-7") {
      if (charCount <= 160) {
        segments = 1;
        segmentLimit = 160;
        charsRemaining = 160 - charCount;
      } else {
        segmentLimit = 153;
        segments = Math.ceil(charCount / 153);
        charsRemaining = segments * 153 - charCount;
      }
    } else {
      // UNICODE
      if (charCount <= 70) {
        segments = 1;
        segmentLimit = 70;
        charsRemaining = 70 - charCount;
      } else {
        segmentLimit = 67;
        segments = Math.ceil(charCount / 67);
        charsRemaining = segments * 67 - charCount;
      }
    }

    return {
      charCount,
      encoding,
      segments,
      charsRemaining,
      isMultipart: segments > 1,
      nonGsmChars,
    };
  }, [content]);

  return (
    <div className="bg-slate-900/80 border border-slate-800 rounded-lg p-3 text-xs space-y-2">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div className="flex items-center space-x-2">
          <span
            className={`font-mono text-xs px-2 py-0.5 rounded font-bold border ${
              metrics.encoding === "GSM-7"
                ? "bg-emerald-500/10 text-emerald-400 border-emerald-500/30"
                : "bg-amber-500/10 text-amber-400 border-amber-500/30"
            }`}
          >
            {metrics.encoding}
          </span>
          <span className="text-slate-300">
            {metrics.charCount} caracteres
          </span>
        </div>

        <div className="flex items-center space-x-2 font-mono text-xs">
          <span className="text-slate-400">
            Segmentos:{" "}
            <strong className="text-white">{metrics.segments}</strong>
          </span>
          <span className="text-slate-600">•</span>
          <span className="text-slate-400">
            Restam no segmento:{" "}
            <strong className="text-emerald-400">
              {metrics.charsRemaining}
            </strong>
          </span>
        </div>
      </div>

      {metrics.encoding === "UNICODE" && metrics.nonGsmChars.length > 0 && (
        <div className="flex items-start space-x-2 bg-amber-950/30 border border-amber-800/40 rounded p-2 text-amber-300 text-[11px]">
          <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5 text-amber-400" />
          <div>
            <strong>Codificação Unicode acionada:</strong> Foi detectado caractere
            não-GSM7 (ex: {metrics.nonGsmChars.slice(0, 5).map((c) => `"${c}"`).join(", ")}). O
            limite por segmento foi reduzido de 160 para 70 caracteres.
          </div>
        </div>
      )}

      {metrics.isMultipart && (
        <div className="flex items-start space-x-2 bg-blue-950/30 border border-blue-800/40 rounded p-2 text-blue-300 text-[11px]">
          <Info className="w-4 h-4 shrink-0 mt-0.5 text-blue-400" />
          <div>
            <strong>Mensagem multipart ({metrics.segments} SMSs):</strong> Operadoras
            cobram 1 crédito de SMS para cada segmento individual entregue.
          </div>
        </div>
      )}
    </div>
  );
}
