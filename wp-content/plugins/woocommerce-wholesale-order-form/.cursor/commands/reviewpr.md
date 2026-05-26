Please analyze the code changes and provide the following information:

1. Run the command below but if the command's result is too long or big, export the command result to a file (`./.ai-context/PR_diff.patch`):
```
git diff trunk...HEAD -- . ':!dist' ':!node_modules' ':!*.lock' ':!pnpm-lock.yaml' ':!package-lock.json' --name-status
```
2. use @Browser to check if changes or updates are related to UI/UX.
3. **Intent and Purpose**: Summarize the goal of the changes. Explain what functionality or behavior the changes aim to introduce, modify, or fix.
4. **Potential Issues**: Identify any possible problems the changes might introduce, such as:
   - Bugs or unintended side effects.
   - Performance concerns.
   - Code quality issues (e.g., readability, maintainability, scalability).
   - Violations of best practices or standards.
   - Potential security vulnerabilities.
5. **Suggestions for Improvement**: Recommend any improvements or alternative approaches that could enhance the code.
