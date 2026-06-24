import { createClient } from 'https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2/+esm';

export const SUPABASE_URL  = 'https://wcsckcyxbixcgjrrjoyo.supabase.co';
export const SUPABASE_KEY  = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Indjc2NrY3l4Yml4Y2dqcnJqb3lvIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODIyNzA4MTgsImV4cCI6MjA5Nzg0NjgxOH0.YQ_iaVv0HwE1jdQFPN1uYXeqan36TsIs7AY11CPmjTM';

export const sb = createClient(SUPABASE_URL, SUPABASE_KEY);

// Auth guard — redirect to login if not signed in
export async function requireAuth() {
  const { data: { session } } = await sb.auth.getSession();
  if (!session) { window.location.href = 'login.html'; return null; }
  return session.user;
}

// Subscription check
const ADMIN_EMAIL = 'tusharbali855@gmail.com';
export async function checkSubscription(email) {
  if (!email) return { status: 'no_plan' };
  if (email.toLowerCase() === ADMIN_EMAIL.toLowerCase()) return { status: 'admin' };
  const { data } = await sb.from('subscriptions').select('*').eq('email', email).single();
  if (!data) return { status: 'no_plan' };
  const expiry = new Date(data.expiry);
  const now    = new Date();
  if (expiry < now) return { status: 'expired', plan: data.plan, billing: data.billing, expiry: data.expiry };
  const daysLeft = Math.ceil((expiry - now) / 86400000);
  return { status: 'active', plan: data.plan, billing: data.billing, expiry: data.expiry, daysLeft };
}
