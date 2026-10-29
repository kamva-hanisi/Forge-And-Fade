export default function FormGuard({ value, onChange }) {
  return (
    <div className="form-guard" aria-hidden="true">
      <label>
        Leave this field empty
        <input
          name="website"
          type="text"
          autoComplete="off"
          tabIndex={-1}
          value={value}
          onChange={(event) => onChange(event.target.value)}
        />
      </label>
    </div>
  );
}
