export interface Role {
  id: number;
  name: string;
  slug: string;
}

export interface Permission {
  id: number;
  name: string;
  slug: string;
  group: string;
}

export interface Platform {
  id: number;
  uuid: string;
  name: string;
  slug: string;
  status: string;
}

export interface User {
  id: number;
  name: string;
  email: string;
  status: string;
  two_factor_enabled: boolean;
  roles: Role[];
  permissions: Permission[];
  platforms: Platform[];
}

export interface AuthResponse {
  success: boolean;
  message: string;
  data: {
    token: string;
    token_type: string;
    user: User;
  };
}

const API_BASE =
  process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api/v1";

export const authService = {
  getToken(): string | null {
    if (typeof window === "undefined") return null;
    return localStorage.getItem("bet_crm_token");
  },

  setToken(token: string): void {
    if (typeof window !== "undefined") {
      localStorage.setItem("bet_crm_token", token);
    }
  },

  getActivePlatform(): Platform | null {
    if (typeof window === "undefined") return null;
    const raw = localStorage.getItem("bet_crm_platform");
    return raw ? JSON.parse(raw) : null;
  },

  setActivePlatform(platform: Platform | null): void {
    if (typeof window !== "undefined") {
      if (platform) {
        localStorage.setItem("bet_crm_platform", JSON.stringify(platform));
      } else {
        localStorage.removeItem("bet_crm_platform");
      }
    }
  },

  clearSession(): void {
    if (typeof window !== "undefined") {
      localStorage.removeItem("bet_crm_token");
      localStorage.removeItem("bet_crm_user");
      localStorage.removeItem("bet_crm_platform");
    }
  },

  async login(email: string, password: string, platformId?: number): Promise<AuthResponse> {
    const res = await fetch(`${API_BASE}/auth/login`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({ email, password, platform_id: platformId }),
    });

    const json = await res.json();

    if (!res.ok || !json.success) {
      throw new Error(json.message || "Erro ao realizar login.");
    }

    this.setToken(json.data.token);
    if (typeof window !== "undefined") {
      localStorage.setItem("bet_crm_user", JSON.stringify(json.data.user));
    }

    // Set default platform if available
    if (json.data.user.platforms?.length > 0) {
      this.setActivePlatform(json.data.user.platforms[0]);
    }

    return json;
  },

  async getMe(): Promise<User> {
    const token = this.getToken();
    if (!token) throw new Error("Não autenticado.");

    const platform = this.getActivePlatform();
    const headers: Record<string, string> = {
      Authorization: `Bearer ${token}`,
      Accept: "application/json",
    };

    if (platform) {
      headers["X-Platform-Id"] = String(platform.id);
    }

    const res = await fetch(`${API_BASE}/auth/me`, { headers });
    const json = await res.json();

    if (!res.ok || !json.success) {
      this.clearSession();
      throw new Error(json.message || "Sessão expirada.");
    }

    if (typeof window !== "undefined") {
      localStorage.setItem("bet_crm_user", JSON.stringify(json.data));
    }

    return json.data;
  },

  async logout(): Promise<void> {
    const token = this.getToken();
    if (token) {
      try {
        await fetch(`${API_BASE}/auth/logout`, {
          method: "POST",
          headers: {
            Authorization: `Bearer ${token}`,
            Accept: "application/json",
          },
        });
      } catch (e) {
        // Ignore network failure on logout
      }
    }
    this.clearSession();
  },

  async revokeAllTokens(): Promise<void> {
    const token = this.getToken();
    if (!token) return;

    await fetch(`${API_BASE}/auth/revoke`, {
      method: "POST",
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "application/json",
      },
    });

    this.clearSession();
  },
};
