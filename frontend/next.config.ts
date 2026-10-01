import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  reactStrictMode: true,
  async rewrites() {
    return [
      {
        source: "/api/backend/:path*",
        destination: "http://localhost:8000/api/v1/:path*",
      },
      {
        source: "/health/:path*",
        destination: "http://localhost:8000/health/:path*",
      },
    ];
  },
};

export default nextConfig;
