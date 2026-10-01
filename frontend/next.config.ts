import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  reactStrictMode: true,
  async rewrites() {
    const rawApiUrl = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api/v1";
    const backendOrigin = rawApiUrl.replace(/\/api\/v1\/?$/, "");
    return [
      {
        source: "/api/backend/:path*",
        destination: `${rawApiUrl}/:path*`,
      },
      {
        source: "/health/:path*",
        destination: `${backendOrigin}/health/:path*`,
      },
    ];
  },
};

export default nextConfig;
