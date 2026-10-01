"use client";

import { useEffect, useState, useCallback } from "react";
import { authService, Platform, User } from "@/services/auth-service";
import { useRouter } from "next/navigation";

export function useAuth(requireAuth = true) {
  const router = useRouter();
  const [user, setUser] = useState<User | null>(null);
  const [activePlatform, setActivePlatform] = useState<Platform | null>(null);
  const [loading, setLoading] = useState(true);

  const refreshUser = useCallback(async () => {
    try {
      const data = await authService.getMe();
      setUser(data);
      const currentPlatform = authService.getActivePlatform();
      if (currentPlatform) {
        setActivePlatform(currentPlatform);
      } else if (data.platforms?.length > 0) {
        setActivePlatform(data.platforms[0]);
        authService.setActivePlatform(data.platforms[0]);
      }
    } catch (err) {
      setUser(null);
      if (requireAuth) {
        router.replace("/login");
      }
    } finally {
      setLoading(false);
    }
  }, [requireAuth, router]);

  useEffect(() => {
    const token = authService.getToken();
    if (!token && requireAuth) {
      setLoading(false);
      router.replace("/login");
      return;
    }

    if (token) {
      refreshUser();
    } else {
      setLoading(false);
    }
  }, [refreshUser, requireAuth, router]);

  const switchPlatform = (platform: Platform) => {
    setActivePlatform(platform);
    authService.setActivePlatform(platform);
  };

  const logout = async () => {
    await authService.logout();
    setUser(null);
    setActivePlatform(null);
    router.replace("/login");
  };

  return {
    user,
    activePlatform,
    loading,
    switchPlatform,
    logout,
    refreshUser,
  };
}
