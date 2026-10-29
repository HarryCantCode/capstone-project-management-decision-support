# Git Auto-Commit Prompt Rule

## Purpose
Every time changes are made to the codebase (features, bug fixes, UI updates, refactorings, or database migrations) and validated:
1. **Never commit and push silently without user confirmation.**
2. **Always ask the user for confirmation** to commit and push changes to their GitHub repository.
3. Present a clear, concise summary of the changes and the proposed commit message.
4. Upon user approval, stage the relevant files, commit with a descriptive conventional commit message, and push to the active branch on GitHub (`origin`).
