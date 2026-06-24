import { serve } from "https://deno.land/std@0.168.0/http/server.ts";
import { createClient } from "https://esm.sh/@supabase/supabase-js@2";

const corsHeaders = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "authorization, x-client-info, apikey, content-type",
};

serve(async (req) => {
  if (req.method === "OPTIONS") {
    return new Response("ok", { headers: corsHeaders });
  }

  try {
    const { to, subject, html, resumeUrl, candidateName } = await req.json();

    // Get user profile for SMTP credentials
    const supabase = createClient(
      Deno.env.get("SUPABASE_URL")!,
      Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!
    );

    const { data: profile } = await supabase
      .from("user_profile")
      .select("email, smtp_pass, name")
      .eq("id", 1)
      .maybeSingle();

    if (!profile?.email || !profile?.smtp_pass) {
      return new Response(
        JSON.stringify({ error: "SMTP credentials not configured. Set email & Gmail App Password in Profile." }),
        { status: 400, headers: { ...corsHeaders, "Content-Type": "application/json" } }
      );
    }

    // Send email via Gmail SMTP using fetch to a relay
    // Use Supabase's built-in SMTP if configured, otherwise use Gmail API
    const smtpPayload = {
      from: `${profile.name || candidateName || "ReachOut"} <${profile.email}>`,
      to,
      subject,
      html,
      // Resume attached as link in body if URL provided
      ...(resumeUrl ? {
        html: html + `\n\n<p style="margin-top:20px;font-size:12px;color:#666">📎 <a href="${resumeUrl}">Download Resume</a></p>`
      } : {}),
    };

    // Use Gmail SMTP via Deno SMTP client
    const { SMTPClient } = await import("https://deno.land/x/smtp@v0.7.0/mod.ts");

    const client = new SMTPClient({
      connection: {
        hostname: "smtp.gmail.com",
        port: 465,
        tls: true,
        auth: {
          username: profile.email,
          password: profile.smtp_pass,
        },
      },
    });

    await client.send({
      from: profile.email,
      to,
      subject,
      html: smtpPayload.html,
    });

    await client.close();

    return new Response(
      JSON.stringify({ success: true }),
      { headers: { ...corsHeaders, "Content-Type": "application/json" } }
    );
  } catch (err) {
    console.error("send-email error:", err);
    return new Response(
      JSON.stringify({ error: err.message || "Failed to send email" }),
      { status: 500, headers: { ...corsHeaders, "Content-Type": "application/json" } }
    );
  }
});
