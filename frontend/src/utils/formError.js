export function formError(error) {
  const message =
    Object.values(error.response?.data?.errors || {})
      .flat()
      .join(" ") ||
    error.response?.data?.message ||
    "Unable to connect. Please try again.";
  if (error.response?.status !== 429) return message;
  const retry = Number(error.response.headers?.["retry-after"]);
  return Number.isFinite(retry) && retry > 0
    ? `${message} Try again in ${Math.ceil(retry)} seconds. Your details are still here.`
    : `${message} Your details are still here.`;
}
