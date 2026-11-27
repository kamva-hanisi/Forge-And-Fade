# Forge & Fade project instructions

Use Mailpit for all local email work until the user asks to change the mail setup. The active local site uses SMTP at 127.0.0.1:1025 and the Mailpit inbox at http://localhost:8025. Check generated invitations, password recovery, and booking emails in that inbox. Keep automated tests isolated with fake or test mail transports. Use external mail delivery only when the user explicitly asks for it.
