import { format, parseISO } from 'date-fns';

/**
 * Get translated text from window object.
 *
 * @param key The array key of the translated text.
 * @returns string
 */
export const getI18n = (key: string): string => {
  const text = (window as any).wwlc_lap?.i18n[key];

  return text ?? key;
}

/**
 * Get list of registered user roles.
 *
 * @returns Array of user roles
 */
export const userRoles = ():Map<string, string>[] => {
  return (window as any).wwlc_lap.roles;
}

/**
 * Get role display name
 *
 * @param role_key The role key.
 * @returns string
 */
export const getRoleName = (role_key:string) => {
  return (window as any).wwlc_lap?.roles[role_key];
}

/**
 * Get the color of the user status.
 *
 * @param status The status of the user.
 * @param role The role of the user.
 * @returns The color of the user status.
 */
export const getUserStatusColor = (status:string, role:string) => {
  switch (getUserStatus(status, role)) {
    case 'approved':
      return 'success';
    case 'rejected':
      return 'error';
    case 'inactive':
      return 'warning';
    case 'pending':
      return 'processing';
    default:
      return 'default';
  }
}

/**
 * Get the status of the user.
 *
 * @param status The status of the user.
 * @param role The role of the user.
 * @returns The status of the user.
 */
export const getUserStatus = (status:string, role:string | undefined) => {
  const roleStatus = role ? role.replace('wwlc_', '') : '';
  const formattedStatus = status ? status.replace('wwlc_', '') : '';

  return formattedStatus !== '' ? formattedStatus : roleStatus;
}


/**
 * Format date string to readable format.
 *
 * @param dateString The date to format.
 * @param dateFormat The format to use. Defaults to 'MMM. dd, yyyy'
 * @returns string
 */
export const formatDate = ( dateString:string, dateFormat = 'MMM. dd, yyyy') => {
  const date = parseISO(dateString);
  return format(date, dateFormat);
}

/**
 * Handles the response.
 *
 * @param response The response object.
 * @param store The pinia store.
 *
 * @returns mixed The response data.
*/
export function handleResponse(response: any, store: any) {
  store.isLoading = false;
  let responseData = null;
  if (response.error) {
    store.hasResults = false;
    store.error = response.data.message;
  } else {
    store.hasResults = true;
    responseData = response.data.data;
  }

  return responseData;
}

/**
 * Handle the error.
 *
 * Reset state when there is an error.
 *
 * @param error Error object.
 * @param store The pinia store object.
 */
export function handleError(error: any, store: any) {
  store.error = error.data.message;
  store.isLoading = false;
  store.hasResults = false;
}

/**
 * Capitalize a string
 *
 * @param string The string to convert to ucFirst
 * @returns
 */
export function ucFirst(string:string) {
  return string.charAt(0).toUpperCase() + string.slice(1);
}