import { useId, useState } from "react";
import { Eye, EyeOff } from "lucide-react";
import "./PasswordField.scss";

export default function PasswordField({
  label,
  name,
  value,
  onChange,
  autoComplete,
  minLength,
  showStrength = false,
  hint,
}) {
  const id = useId();
  const [visible, setVisible] = useState(false);
  const length = Array.from(value).length;
  const level = length >= 16 ? "strong" : length >= 12 ? "medium" : "weak";
  const strength = level[0].toUpperCase() + level.slice(1);
  const hasStrength = showStrength && length > 0;
  const description = [hint && `${id}-hint`, hasStrength && `${id}-strength`]
    .filter(Boolean)
    .join(" ");

  return (
    <div className="password-field">
      <label htmlFor={id}>{label}</label>
      <div className="password-field__control">
        <input
          id={id}
          name={name}
          type={visible ? "text" : "password"}
          autoComplete={autoComplete}
          minLength={minLength}
          maxLength={200}
          required
          value={value}
          onChange={onChange}
          aria-describedby={description || undefined}
        />
        <button
          type="button"
          aria-controls={id}
          aria-label={`${visible ? "Hide" : "Show"} ${label.toLowerCase()}`}
          onClick={() => setVisible((shown) => !shown)}
        >
          {visible ? <EyeOff size={18} aria-hidden="true" /> : <Eye size={18} aria-hidden="true" />}
        </button>
      </div>
      {hasStrength && (
        <div className="password-field__strength" data-level={level}>
          <div className="password-field__bar" aria-hidden="true">
            <span />
            <span />
            <span />
          </div>
          <small id={`${id}-strength`}>
            {strength} by length · {length} {length === 1 ? "character" : "characters"}
          </small>
        </div>
      )}
      {hint && <small id={`${id}-hint`}>{hint}</small>}
    </div>
  );
}
